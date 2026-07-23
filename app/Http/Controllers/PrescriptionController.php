<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesClinic;
use App\Http\Requests\StorePrescriptionRequest;
use App\Http\Requests\UpdatePrescriptionRequest;
use App\Mail\PrescriptionMail;
use App\Models\Clinic;
use App\Models\Consultation;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\PrescriptionAuthorizationAudit;
use App\Services\PrescriptionSignAudit;
use App\Support\ControlledResponse;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class PrescriptionController extends Controller
{
    use ResolvesClinic;

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

    public function create(Request $request): View
    {
        $this->ensurePrescriptionCreatorIsAllowed();
        $clinicId = $this->clinicId();
        $consultationId = $request->integer('consultation_id');
        $consultation = $consultationId
            ? $this->consultationForPrescriptionCreate($consultationId, $clinicId)
            : null;

        return view('prescriptions.create', $this->formData($clinicId, $consultation));
    }

    public function store(StorePrescriptionRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $clinicId = $this->clinicId();
        $prescription = null;

        DB::transaction(function () use ($validated, $clinicId, &$prescription): void {
            $data = $this->prepareCreateData($validated, $clinicId);
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
        $this->authorizePrescriptionAbility($prescription, 'view', 'view');

        return view('prescriptions.show', [
            'prescription' => $prescription->load(['patient', 'doctor.user', 'doctor.specialty', 'consultation.appointment', 'items', 'signedByUser']),
        ]);
    }

    public function edit(Request $request, Prescription $prescription): View|Response
    {
        $decision = Gate::inspect('update', $prescription);

        if ($decision->denied()) {
            app(PrescriptionAuthorizationAudit::class)->record(
                prescription: $prescription,
                operation: 'update',
                reason: (string) $decision->message(),
            );

            return $this->prescriptionDenialResponse(
                $request,
                $prescription,
                (string) $decision->message(),
                'update',
            );
        }

        return view('prescriptions.edit', [
            'prescription' => $prescription->load(['items', 'patient', 'doctor.user', 'consultation']),
        ]);
    }

    public function update(UpdatePrescriptionRequest $request, Prescription $prescription): RedirectResponse|JsonResponse
    {
        $actorId = (int) $request->user()->id;
        $validated = $request->validated();

        try {
            $outcome = DB::transaction(function () use ($request, $prescription, $actorId, $validated): array {
                $actor = $this->lockActorContext($actorId);
                $lockedPrescription = Prescription::query()
                    ->lockForUpdate()
                    ->findOrFail($prescription->getKey());
                $lockedPrescription->setRelation(
                    'patient',
                    Patient::query()->lockForUpdate()->find($lockedPrescription->patient_id),
                );
                $lockedPrescription->setRelation(
                    'doctor',
                    Doctor::query()->lockForUpdate()->find($lockedPrescription->doctor_id),
                );
                if ($lockedPrescription->consultation_id !== null) {
                    $lockedPrescription->setRelation(
                        'consultation',
                        Consultation::query()
                            ->lockForUpdate()
                            ->findOrFail($lockedPrescription->consultation_id),
                    );
                }
                $lockedItems = $lockedPrescription->items()->lockForUpdate()->get();
                $lockedPrescription->setRelation('items', $lockedItems);
                $request->setUserResolver(static fn (): User => $actor);

                $decision = Gate::forUser($actor)->inspect('update', $lockedPrescription);

                if ($decision->denied()) {
                    app(PrescriptionAuthorizationAudit::class)->record(
                        prescription: $lockedPrescription,
                        operation: 'update',
                        reason: (string) $decision->message(),
                    );

                    return ['result' => 'denied', 'reason' => (string) $decision->message()];
                }

                $data = $this->prepareUpdateData($validated);
                $old = [
                    'status' => $lockedPrescription->status,
                    'consultation_attached' => $lockedPrescription->consultation_id !== null,
                    'item_count' => $lockedItems->count(),
                ];

                $lockedPrescription->update($data['prescription']);
                $lockedPrescription->items()->delete();
                $lockedPrescription->items()->createMany($data['items']);
                $updatedPrescription = $lockedPrescription->refresh();
                $updatedPrescription->setRelation('patient', $lockedPrescription->patient);
                $audit = AuditLogger::log(
                    $updatedPrescription->status === 'cancelled'
                        ? 'prescription.cancelled'
                        : 'prescription.updated',
                    'prescriptions',
                    $updatedPrescription,
                    $old,
                    [
                        'status' => $updatedPrescription->status,
                        'consultation_attached' => $updatedPrescription->consultation_id !== null,
                        'item_count' => count($data['items']),
                    ],
                    'Receta médica actualizada.',
                );

                if (! $audit) {
                    throw new RuntimeException('Prescription update audit could not be persisted.');
                }

                return ['result' => 'success', 'reason' => null];
            });
        } catch (ModelNotFoundException) {
            return ControlledResponse::error($request, 404, 'RESOURCE_NOT_FOUND');
        } catch (ValidationException $exception) {
            if ($request->expectsJson()) {
                return ControlledResponse::jsonError(422, 'VALIDATION_ERROR', [
                    'errors' => $exception->errors(),
                ]);
            }

            throw $exception;
        } catch (Throwable $exception) {
            Log::error('Prescription update transaction failed.', [
                'prescription_id' => (int) $prescription->getKey(),
                'actor_user_id' => $actorId,
                'exception_class' => $exception::class,
            ]);

            if ($request->expectsJson()) {
                return ControlledResponse::jsonError(500, 'OPERATION_FAILED');
            }

            return back()->with('error', 'No se pudo completar la actualización de la receta. Inténtelo nuevamente.');
        }

        if ($outcome['result'] === 'denied') {
            return $this->prescriptionDenialResponse(
                $request,
                $prescription,
                $outcome['reason'],
                'update',
            );
        }

        if ($request->expectsJson()) {
            return ControlledResponse::jsonSuccess('OPERATION_COMPLETED');
        }

        return redirect()
            ->route('prescriptions.show', $prescription)
            ->with('success', 'Receta actualizada correctamente.');
    }

    public function destroy(Request $request, Prescription $prescription): RedirectResponse|JsonResponse
    {
        $actorId = (int) $request->user()->id;
        try {
            $outcome = DB::transaction(function () use ($request, $prescription, $actorId): array {
            $actor = $this->lockActorContext($actorId);
            $lockedPrescription = Prescription::query()
                ->lockForUpdate()
                ->findOrFail($prescription->getKey());
            $lockedPrescription->setRelation(
                'patient',
                Patient::query()->lockForUpdate()->find($lockedPrescription->patient_id),
            );
            $lockedPrescription->setRelation(
                'doctor',
                Doctor::query()->lockForUpdate()->find($lockedPrescription->doctor_id),
            );
            $lockedPrescription->setRelation(
                'items',
                $lockedPrescription->items()->lockForUpdate()->get(),
            );
            $request->setUserResolver(static fn (): User => $actor);

            $decision = Gate::forUser($actor)->inspect('delete', $lockedPrescription);

            if ($decision->denied()) {
                app(PrescriptionAuthorizationAudit::class)->record(
                    prescription: $lockedPrescription,
                    operation: 'delete',
                    reason: (string) $decision->message(),
                );

                return ['result' => 'denied', 'reason' => (string) $decision->message()];
            }

            $audit = AuditLogger::log(
                'prescription.deleted',
                'prescriptions',
                $lockedPrescription,
                [
                    'status' => $lockedPrescription->status,
                    'item_count' => $lockedPrescription->items->count(),
                ],
                [],
                'Receta médica eliminada.',
            );

            if (! $audit) {
                throw new RuntimeException('Prescription delete audit could not be persisted.');
            }

            $lockedPrescription->delete();

            return ['result' => 'success', 'reason' => null];
        });
        } catch (ModelNotFoundException) {
            return ControlledResponse::error($request, 404, 'RESOURCE_NOT_FOUND');
        } catch (Throwable $exception) {
            Log::error('Prescription delete transaction failed.', [
                'prescription_id' => (int) $prescription->getKey(),
                'actor_user_id' => $actorId,
                'exception_class' => $exception::class,
            ]);

            if ($request->expectsJson()) {
                return ControlledResponse::jsonError(500, 'OPERATION_FAILED');
            }

            return back()->with('error', 'No se pudo completar la eliminación de la receta. Inténtelo nuevamente.');
        }

        if ($outcome['result'] === 'denied') {
            return $this->prescriptionDenialResponse(
                $request,
                $prescription,
                $outcome['reason'],
                'delete',
            );
        }

        if ($request->expectsJson()) {
            return ControlledResponse::jsonSuccess('OPERATION_COMPLETED');
        }

        return redirect()
            ->route('prescriptions.index')
            ->with('success', 'Receta eliminada correctamente.');
    }

    public function print(Prescription $prescription): View
    {
        $this->authorizePrescriptionAbility($prescription, 'view', 'view');
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
        $this->authorizePrescriptionAbility($prescription, 'view', 'view');
        $prescription = $this->loadForDelivery($prescription);
        $this->markAsPrinted($prescription);
        AuditLogger::log('prescription.pdf_downloaded', 'prescriptions', $prescription->load('patient'), [], ['prescription_id' => $prescription->id], 'PDF de receta descargado.');

        return $this->pdfFor($prescription->refresh())
            ->download($this->pdfFileName($prescription));
    }

    public function sendEmail(Request $request, Prescription $prescription): RedirectResponse
    {
        $this->authorizePrescriptionAbility($prescription, 'send', 'send');

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

            return back()->with('error', 'No se pudo enviar la receta por correo. Revise la configuración de correo e inténtelo nuevamente.');
        }

        return back()->with('success', 'Receta enviada por correo correctamente.');
    }

    public function sign(
        Request $request,
        Prescription $prescription,
        PrescriptionSignAudit $signAudit,
    ): RedirectResponse|JsonResponse {
        $actorId = (int) $request->user()->id;

        try {
            $outcome = DB::transaction(function () use ($request, $prescription, $signAudit, $actorId): array {
                $actor = $this->lockActorContext($actorId);

                $lockedPrescription = Prescription::query()
                    ->lockForUpdate()
                    ->findOrFail($prescription->getKey());
                $lockedPrescription->setRelation(
                    'patient',
                    Patient::query()->lockForUpdate()->find($lockedPrescription->patient_id),
                );
                $lockedPrescription->setRelation(
                    'doctor',
                    Doctor::query()->lockForUpdate()->find($lockedPrescription->doctor_id),
                );
                $lockedPrescription->setRelation(
                    'items',
                    $lockedPrescription->items()->lockForUpdate()->get(),
                );
                $request->setUserResolver(static fn (): User => $actor);

                $decision = Gate::forUser($actor)->inspect('sign', $lockedPrescription);

                if ($decision->denied()) {
                    $reason = $signAudit->normalizeReason($decision->message());

                    $signAudit->record(
                        request: $request,
                        prescription: $lockedPrescription,
                        result: 'denied',
                        reason: $reason,
                    );

                    return [
                        'result' => 'denied',
                        'reason' => $reason,
                    ];
                }

                $lockedPrescription->forceFill([
                    'signed_at' => now(),
                    'signed_by_user_id' => $actor->id,
                    'signature_verification_code' => $lockedPrescription->generateVerificationCode(),
                    'signature_hash' => $lockedPrescription->calculateSignatureHash(),
                    'signed_ip_address' => $request->ip(),
                    'signed_user_agent' => $request->userAgent(),
                ])->save();

                if (! $signAudit->record(
                    request: $request,
                    prescription: $lockedPrescription,
                    result: 'success',
                )) {
                    throw new RuntimeException('Prescription sign audit could not be persisted.');
                }

                return [
                    'result' => 'success',
                    'reason' => null,
                ];
            });
        } catch (ModelNotFoundException) {
            return ControlledResponse::error($request, 404, 'RESOURCE_NOT_FOUND');
        } catch (Throwable $exception) {
            Log::error('Prescription signing transaction failed.', [
                'prescription_id' => (int) $prescription->getKey(),
                'actor_user_id' => $actorId,
                'exception_class' => $exception::class,
            ]);

            if ($request->expectsJson()) {
                return ControlledResponse::jsonError(500, 'OPERATION_FAILED');
            }

            return back()->with('error', 'No se pudo completar la firma de la receta. Inténtelo nuevamente.');
        }

        if ($outcome['result'] === 'denied') {
            return $this->prescriptionDenialResponse(
                $request,
                $prescription,
                $outcome['reason'],
                'sign',
            );
        }

        if ($request->expectsJson()) {
            return ControlledResponse::jsonSuccess('OPERATION_COMPLETED');
        }

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

    private function prepareCreateData(array $validated, int $clinicId): array
    {
        $patient = Patient::where('clinic_id', $clinicId)->where('status', 'active')
            ->lockForUpdate()->find($validated['patient_id']);
        $doctor = Doctor::where('clinic_id', $clinicId)->where('status', 'active')
            ->lockForUpdate()->find($validated['doctor_id']);
        $consultation = isset($validated['consultation_id']) && $validated['consultation_id']
            ? Consultation::whereHas('patient', fn ($query) => $query->where('clinic_id', $clinicId))
                ->lockForUpdate()
                ->find($validated['consultation_id'])
            : null;

        if (! $patient || ! $doctor || (($validated['consultation_id'] ?? null) && ! $consultation)) {
            throw ValidationException::withMessages([
                'clinic_id' => 'Los datos seleccionados no pertenecen a la clínica del usuario autenticado.',
            ]);
        }
        if ($this->isDoctorUser()) {
            $authenticatedDoctor = $this->authenticatedDoctor($clinicId);

            if (! $authenticatedDoctor || (int) $doctor->id !== (int) $authenticatedDoctor->id) {
                throw ValidationException::withMessages([
                    'doctor_id' => 'No puede crear recetas para otro medico.',
                ]);
            }
        } elseif (! $this->canDelegatePrescriptionDoctor()) {
            throw ValidationException::withMessages([
                'doctor_id' => 'No puede crear recetas para otro medico.',
            ]);
        }

        if ($consultation && ((int) $consultation->patient_id !== (int) $patient->id || (int) $consultation->doctor_id !== (int) $doctor->id)) {
            throw ValidationException::withMessages([
                'consultation_id' => 'La consulta seleccionada no coincide con el paciente y médico indicados.',
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
            'items' => $this->prepareItems($validated['items']),
        ];
    }

    private function prepareUpdateData(array $validated): array
    {
        return [
            'prescription' => [
                'prescription_date' => $validated['prescription_date'],
                'general_instructions' => $validated['general_instructions'] ?? null,
                'status' => $validated['status'],
            ],
            'items' => $this->prepareItems($validated['items']),
        ];
    }

    private function prepareItems(array $items): array
    {
        return collect($items)->map(fn ($item) => [
            'medication_name' => $item['medication_name'],
            'dosage' => $item['dosage'] ?? null,
            'frequency' => $item['frequency'] ?? null,
            'duration' => $item['duration'] ?? null,
            'instructions' => $item['instructions'] ?? null,
        ])->all();
    }

    private function lockActorContext(int $actorId): User
    {
        $actor = User::query()->lockForUpdate()->findOrFail($actorId);
        $clinicId = (int) ($actor->current_clinic_id ?? 0);

        if ($clinicId !== 0) {
            Clinic::query()
                ->whereKey($clinicId)
                ->lockForUpdate()
                ->first();

            DB::table('clinic_user')
                ->where('clinic_id', $clinicId)
                ->where('user_id', $actor->id)
                ->lockForUpdate()
                ->first();
        }

        return $actor;
    }

    private function prescriptionDenialResponse(
        Request $request,
        Prescription $prescription,
        string $reason,
        string $operation,
    ): Response {
        if ($reason === 'wrong_clinic') {
            return ControlledResponse::error($request, 404, 'RESOURCE_NOT_FOUND');
        }

        if (in_array($reason, ['already_signed', 'cancelled', 'invalid_status'], true)) {
            if ($request->expectsJson()) {
                return ControlledResponse::jsonError(409, 'RESOURCE_STATE_CONFLICT');
            }

            if ($operation === 'delete') {
                return back()->with('error', 'No se puede eliminar esta receta.');
            }

            if ($operation === 'sign') {
                return back()->with(
                    'error',
                    $reason === 'already_signed'
                        ? 'La receta ya está firmada.'
                        : 'No se puede firmar una receta cancelada.',
                );
            }

            return redirect()
                ->route('prescriptions.show', $prescription)
                ->with('error', 'No se puede editar una receta firmada. Anule o cree una nueva receta.');
        }

        return ControlledResponse::error($request, 403, 'OPERATION_NOT_AUTHORIZED');
    }

    private function authorizePrescriptionAbility(
        Prescription $prescription,
        string $ability,
        string $operation,
    ): void {
        $decision = Gate::inspect($ability, $prescription);

        if ($decision->allowed()) {
            return;
        }

        $reason = (string) $decision->message();
        app(PrescriptionAuthorizationAudit::class)->record(
            prescription: $prescription,
            operation: $operation,
            reason: $reason,
        );

        throw new HttpResponseException(
            $this->prescriptionDenialResponse(request(), $prescription, $reason, $operation),
        );
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

    private function consultationForPrescriptionCreate(int $consultationId, int $clinicId): Consultation
    {
        $doctor = $this->authenticatedDoctor($clinicId);
        $isDoctorView = $this->isDoctorUser();
        $canDelegate = $this->canDelegatePrescriptionDoctor();

        return Consultation::with(['patient', 'doctor.user'])
            ->whereHas('patient', fn ($query) => $query
                ->where('clinic_id', $clinicId)
                ->where('status', 'active'))
            ->whereHas('doctor', fn ($query) => $query
                ->where('clinic_id', $clinicId)
                ->where('status', 'active'))
            ->when($isDoctorView, function ($query) use ($doctor): void {
                $doctor
                    ? $query->where('doctor_id', $doctor->id)
                    : $query->whereRaw('1 = 0');
            })
            ->when(! $isDoctorView && ! $canDelegate, fn ($query) => $query->whereRaw('1 = 0'))
            ->findOrFail($consultationId);
    }

    private function formData(int $clinicId, ?Consultation $consultation = null): array
    {
        $doctor = $this->authenticatedDoctor($clinicId);
        $isDoctorView = $this->isDoctorUser();
        $canDelegate = $this->canDelegatePrescriptionDoctor();

        return [
            'consultations' => $consultation ? collect([$consultation]) : collect(),
            'patients' => Patient::where('clinic_id', $clinicId)->where('status', 'active')->orderBy('last_name')->orderBy('first_name')->get(),
            'doctors' => $isDoctorView
                ? ($doctor ? collect([$doctor->loadMissing(['user', 'specialty'])]) : collect())
                : ($canDelegate ? $this->doctors($clinicId) : collect()),
            'prefill' => $consultation ? [
                'consultation_id' => $consultation->id,
                'patient_id' => $consultation->patient_id,
                'doctor_id' => $consultation->doctor_id,
            ] : [],
        ];
    }

    private function isDoctorUser(): bool
    {
        return (bool) auth()->user()?->hasRole('medico');
    }

    private function canDelegatePrescriptionDoctor(): bool
    {
        return (bool) auth()->user()?->hasRole('administrador');
    }

    private function ensurePrescriptionCreatorIsAllowed(): void
    {
        $user = auth()->user();

        abort_if(
            ! $user
                || $user->status !== 'active'
                || (! $this->isDoctorUser() && ! $this->canDelegatePrescriptionDoctor()),
            403,
        );
    }

    private function authenticatedDoctor(int $clinicId): ?Doctor
    {
        $user = auth()->user();

        if (! $user?->id) {
            return null;
        }

        return Doctor::where('clinic_id', $clinicId)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->first();
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
