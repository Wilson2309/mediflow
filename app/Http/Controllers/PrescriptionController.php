<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePrescriptionRequest;
use App\Http\Requests\UpdatePrescriptionRequest;
use App\Mail\PrescriptionMail;
use App\Models\Consultation;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Prescription;
use App\Services\AuditLogger;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class PrescriptionController extends Controller
{
    public function index(Request $request): View
    {
        $clinicId = $this->clinicId();
        $search = trim((string) $request->query('search'));
        $doctorId = $request->query('doctor_id');
        $status = $request->query('status');
        $date = $request->query('date');

        $prescriptions = Prescription::query()
            ->with(['patient', 'doctor.user', 'consultation', 'items'])
            ->whereHas('patient', fn ($query) => $query->where('clinic_id', $clinicId))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('general_instructions', 'like', "%{$search}%")
                        ->orWhereHas('patient', function ($query) use ($search) {
                            $query->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('doctor.user', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('items', fn ($query) => $query->where('medication_name', 'like', "%{$search}%"));
                });
            })
            ->when($doctorId, fn ($query) => $query->where('doctor_id', $doctorId))
            ->when(in_array($status, ['active', 'cancelled'], true), fn ($query) => $query->where('status', $status))
            ->when($date, fn ($query) => $query->whereDate('prescription_date', $date))
            ->orderByDesc('prescription_date')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        return view('prescriptions.index', [
            'prescriptions' => $prescriptions,
            'doctors' => $this->doctors($clinicId, onlyActive: false),
            'search' => $search,
            'doctorId' => $doctorId,
            'status' => $status,
            'date' => $date,
        ]);
    }

    public function create(): View
    {
        return view('prescriptions.create', $this->formData($this->clinicId()));
    }

    public function store(StorePrescriptionRequest $request): RedirectResponse
    {
        $data = $this->prepareData($request->validated(), $this->clinicId());

        $prescription = null;

        DB::transaction(function () use ($data, &$prescription) {
            $prescription = Prescription::create($data['prescription']);
            $prescription->items()->createMany($data['items']);
        });

        if ($prescription) {
            AuditLogger::log('prescription.created', 'prescriptions', $prescription->load('patient'), [], AuditLogger::modelSnapshot($prescription), 'Receta medica creada.');
        }

        return redirect()
            ->route('prescriptions.index')
            ->with('success', 'Receta creada correctamente.');
    }

    public function show(Prescription $prescription): View
    {
        $this->authorizeClinic($prescription);

        return view('prescriptions.show', [
            'prescription' => $prescription->load(['patient', 'doctor.user', 'doctor.specialty', 'consultation.appointment', 'items', 'signedByUser']),
        ]);
    }

    public function edit(Prescription $prescription): View|RedirectResponse
    {
        $this->authorizeClinic($prescription);

        if ($prescription->isSigned()) {
            return redirect()
                ->route('prescriptions.show', $prescription)
                ->with('error', 'No se puede editar una receta firmada. Anule o cree una nueva receta.');
        }

        return view('prescriptions.edit', [
            'prescription' => $prescription->load(['items', 'patient', 'doctor.user', 'consultation']),
            ...$this->formData($this->clinicId()),
        ]);
    }

    public function update(UpdatePrescriptionRequest $request, Prescription $prescription): RedirectResponse
    {
        $this->authorizeClinic($prescription);

        if ($prescription->isSigned()) {
            return redirect()
                ->route('prescriptions.show', $prescription)
                ->with('error', 'No se puede editar una receta firmada. Anule o cree una nueva receta.');
        }

        $old = AuditLogger::modelSnapshot($prescription);
        $data = $this->prepareData($request->validated(), $this->clinicId());

        DB::transaction(function () use ($prescription, $data) {
            $prescription->update($data['prescription']);
            $prescription->items()->delete();
            $prescription->items()->createMany($data['items']);
        });

        AuditLogger::log($prescription->status === 'cancelled' ? 'prescription.cancelled' : 'prescription.updated', 'prescriptions', $prescription->refresh()->load('patient'), $old, AuditLogger::modelSnapshot($prescription), 'Receta mÃ©dica actualizada.');

        return redirect()
            ->route('prescriptions.show', $prescription)
            ->with('success', 'Receta actualizada correctamente.');
    }

    public function destroy(Prescription $prescription): RedirectResponse
    {
        $this->authorizeClinic($prescription);
        $old = AuditLogger::modelSnapshot($prescription);
        AuditLogger::log('prescription.deleted', 'prescriptions', $prescription->load('patient'), $old, [], 'Receta mÃ©dica eliminada.');

        $prescription->delete();

        return redirect()
            ->route('prescriptions.index')
            ->with('success', 'Receta eliminada correctamente.');
    }

    public function print(Prescription $prescription): View
    {
        $this->authorizePrescriptionView($prescription);
        $prescription = $this->loadForDelivery($prescription);
        $this->markAsPrinted($prescription);
        $prescription = $prescription->refresh()->load(['patient.clinic', 'doctor.user', 'doctor.specialty', 'consultation', 'items', 'signedByUser']);

        return view('prescriptions.print', [
            'prescription' => $prescription,
            'clinic' => $prescription->patient?->clinic,
            'generatedAt' => now(),
            'forPdf' => false,
            'signatureQrCode' => $this->signatureQrDataUri($prescription),
        ]);
    }

    public function pdf(Prescription $prescription): Response
    {
        $this->authorizePrescriptionView($prescription);
        $prescription = $this->loadForDelivery($prescription);
        $this->markAsPrinted($prescription);
        AuditLogger::log('prescription.pdf_downloaded', 'prescriptions', $prescription->load('patient'), [], ['prescription_id' => $prescription->id], 'PDF de receta descargado.');

        return $this->pdfFor($prescription->refresh())
            ->download($this->pdfFileName($prescription));
    }

    public function sendEmail(Request $request, Prescription $prescription): RedirectResponse
    {
        $this->authorizePrescriptionUpdate($prescription);

        $validated = $request->validate([
            'email' => 'nullable|email|max:255',
        ]);

        $prescription = $this->loadForDelivery($prescription);
        $recipient = ($validated['email'] ?? null) ?: $prescription->patient?->email;

        if (! $recipient) {
            return back()->withErrors([
                'email' => 'El paciente no tiene correo registrado. Ingrese un correo para enviar la receta.',
            ])->withInput();
        }

        try {
            Mail::to($recipient)->send(new PrescriptionMail(
                prescription: $prescription,
                pdfContent: $this->pdfFor($prescription)->output(),
                fileName: $this->pdfFileName($prescription),
            ));

            $old = AuditLogger::modelSnapshot($prescription);
            $prescription->forceFill([
                'last_emailed_at' => now(),
                'last_emailed_to' => $recipient,
                'email_count' => ((int) $prescription->email_count) + 1,
            ])->save();
            AuditLogger::log('prescription.emailed', 'prescriptions', $prescription->load('patient'), $old, ['last_emailed_at' => $prescription->last_emailed_at, 'last_emailed_to' => $recipient, 'email_count' => $prescription->email_count], 'Receta enviada por correo.');
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', 'No se pudo enviar la receta por correo. Revise la configuraciÃ³n de correo e intÃ©ntelo nuevamente.');
        }

        return back()->with('success', 'Receta enviada por correo correctamente.');
    }

    public function sign(Request $request, Prescription $prescription): RedirectResponse
    {
        $this->authorizePrescriptionUpdate($prescription);
        $prescription->loadMissing(['items']);

        if ($prescription->status === 'cancelled') {
            return back()->with('error', 'No se puede firmar una receta cancelada.');
        }

        if ($prescription->isSigned()) {
            return back()->with('error', 'La receta ya está firmada.');
        }

        $old = AuditLogger::modelSnapshot($prescription);

        $prescription->forceFill([
            'signed_at' => now(),
            'signed_by_user_id' => $request->user()?->id,
            'signature_verification_code' => $prescription->generateVerificationCode(),
            'signature_hash' => $prescription->calculateSignatureHash(),
            'signed_ip_address' => $request->ip(),
            'signed_user_agent' => $request->userAgent(),
        ])->save();

        AuditLogger::log('prescription.signed', 'prescriptions', $prescription->load('patient'), $old, [
            'signature_verification_code' => $prescription->signature_verification_code,
            'signed_at' => $prescription->signed_at,
            'signed_by_user_id' => $prescription->signed_by_user_id,
        ], 'Receta firmada electronicamente en MediFlow.');

        return redirect()
            ->route('prescriptions.show', $prescription)
            ->with('success', 'Receta firmada electrónicamente correctamente.');
    }

    public function verify(string $code): View
    {
        $prescription = Prescription::with(['patient.clinic', 'doctor.user', 'doctor.specialty', 'items', 'signedByUser'])
            ->where('signature_verification_code', $code)
            ->first();

        if (! $prescription) {
            return view('prescriptions.verify', [
                'status' => 'not_found',
                'code' => $code,
                'prescription' => null,
                'currentHash' => null,
                'patientName' => null,
                'maskedIdentification' => null,
            ]);
        }

        $currentHash = $prescription->calculateSignatureHash();
        $status = hash_equals((string) $prescription->signature_hash, $currentHash) ? 'valid' : 'altered';

        return view('prescriptions.verify', [
            'status' => $status,
            'code' => $code,
            'prescription' => $prescription,
            'currentHash' => $currentHash,
            'patientName' => $this->maskedPatientName($prescription->patient),
            'maskedIdentification' => $this->maskedIdentification($prescription->patient?->identification_number),
        ]);
    }

    private function prepareData(array $validated, int $clinicId): array
    {
        $patient = Patient::where('clinic_id', $clinicId)->find($validated['patient_id']);
        $doctor = Doctor::where('clinic_id', $clinicId)->find($validated['doctor_id']);
        $consultation = isset($validated['consultation_id']) && $validated['consultation_id']
            ? Consultation::whereHas('patient', fn ($query) => $query->where('clinic_id', $clinicId))->find($validated['consultation_id'])
            : null;

        if (! $patient || ! $doctor || (($validated['consultation_id'] ?? null) && ! $consultation)) {
            throw ValidationException::withMessages([
                'clinic_id' => 'Los datos seleccionados no pertenecen a la clÃ­nica del usuario autenticado.',
            ]);
        }

        if ($consultation && ((int) $consultation->patient_id !== (int) $patient->id || (int) $consultation->doctor_id !== (int) $doctor->id)) {
            throw ValidationException::withMessages([
                'consultation_id' => 'La consulta seleccionada no coincide con el paciente y mÃ©dico indicados.',
            ]);
        }

        return [
            'prescription' => [
                'patient_id' => $patient->id,
                'doctor_id' => $doctor->id,
                'consultation_id' => $consultation?->id,
                'prescription_date' => $validated['prescription_date'],
                'general_instructions' => $validated['general_instructions'] ?? null,
                'status' => $validated['status'],
            ],
            'items' => collect($validated['items'])->map(fn ($item) => [
                'medication_name' => $item['medication_name'],
                'dosage' => $item['dosage'] ?? null,
                'frequency' => $item['frequency'] ?? null,
                'duration' => $item['duration'] ?? null,
                'instructions' => $item['instructions'] ?? null,
            ])->all(),
        ];
    }

    private function clinicId(): int
    {
        $clinicId = auth()->user()?->clinic_id;
        abort_if(! $clinicId, 403, 'El usuario autenticado no tiene una clÃ­nica asignada.');

        return (int) $clinicId;
    }

    private function authorizeClinic(Prescription $prescription): void
    {
        $prescription->loadMissing('patient');
        abort_if((int) $prescription->patient?->clinic_id !== $this->clinicId(), 403);
    }

    private function authorizePrescriptionView(Prescription $prescription): void
    {
        abort_unless(auth()->user()?->can('prescriptions.view'), 403);
        $this->authorizeClinic($prescription);
    }

    private function authorizePrescriptionUpdate(Prescription $prescription): void
    {
        abort_unless(auth()->user()?->can('prescriptions.update'), 403);
        $this->authorizeClinic($prescription);
    }

    private function loadForDelivery(Prescription $prescription): Prescription
    {
        return $prescription->load(['patient.clinic', 'doctor.user', 'doctor.specialty', 'consultation', 'items', 'signedByUser']);
    }

    private function markAsPrinted(Prescription $prescription): void
    {
        $prescription->forceFill([
            'last_printed_at' => now(),
            'print_count' => ((int) $prescription->print_count) + 1,
        ])->save();
    }

    private function pdfFor(Prescription $prescription)
    {
        $prescription->loadMissing(['patient.clinic', 'doctor.user', 'doctor.specialty', 'consultation', 'items', 'signedByUser']);

        return Pdf::loadView('prescriptions.print', [
            'prescription' => $prescription,
            'clinic' => $prescription->patient?->clinic,
            'generatedAt' => now(),
            'forPdf' => true,
            'signatureQrCode' => $this->signatureQrDataUri($prescription),
        ])->setPaper('a4');
    }

    private function signatureQrDataUri(Prescription $prescription): ?string
    {
        if (! $prescription->signature_verification_code) {
            return null;
        }

        $renderer = new ImageRenderer(
            new RendererStyle(132, 1),
            new SvgImageBackEnd()
        );
        $writer = new Writer($renderer);
        $svg = $writer->writeString(route('prescriptions.verify', $prescription->signature_verification_code));

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    private function pdfFileName(Prescription $prescription): string
    {
        return 'receta-medica-REC-'.str_pad((string) $prescription->id, 6, '0', STR_PAD_LEFT).'.pdf';
    }

    private function maskedPatientName(?Patient $patient): string
    {
        if (! $patient) {
            return 'Paciente no disponible';
        }

        $firstName = trim((string) $patient->first_name) ?: 'Paciente';
        $lastInitial = $patient->last_name ? strtoupper(substr((string) $patient->last_name, 0, 1)).'.' : '';

        return trim($firstName.' '.$lastInitial);
    }

    private function maskedIdentification(?string $identification): string
    {
        if (! $identification) {
            return 'No registrada';
        }

        $visible = substr($identification, -3);

        return str_repeat('*', max(strlen($identification) - 3, 0)).$visible;
    }

    private function formData(int $clinicId): array
    {
        return [
            'consultations' => Consultation::with(['patient', 'doctor.user'])
                ->whereHas('patient', fn ($query) => $query->where('clinic_id', $clinicId))
                ->orderByDesc('consultation_date')
                ->get(),
            'patients' => Patient::where('clinic_id', $clinicId)->where('status', 'active')->orderBy('last_name')->orderBy('first_name')->get(),
            'doctors' => $this->doctors($clinicId),
        ];
    }

    private function doctors(int $clinicId, bool $onlyActive = true)
    {
        return Doctor::with(['user', 'specialty'])
            ->where('clinic_id', $clinicId)
            ->when($onlyActive, fn ($query) => $query->where('status', 'active'))
            ->get()
            ->sortBy(fn (Doctor $doctor) => $doctor->user?->name ?? '');
    }
}

