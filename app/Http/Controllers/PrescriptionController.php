<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePrescriptionRequest;
use App\Http\Requests\UpdatePrescriptionRequest;
use App\Models\Consultation;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Prescription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

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

        DB::transaction(function () use ($data) {
            $prescription = Prescription::create($data['prescription']);
            $prescription->items()->createMany($data['items']);
        });

        return redirect()
            ->route('prescriptions.index')
            ->with('success', 'Receta creada correctamente.');
    }

    public function show(Prescription $prescription): View
    {
        $this->authorizeClinic($prescription);

        return view('prescriptions.show', [
            'prescription' => $prescription->load(['patient', 'doctor.user', 'doctor.specialty', 'consultation.appointment', 'items']),
        ]);
    }

    public function edit(Prescription $prescription): View
    {
        $this->authorizeClinic($prescription);

        return view('prescriptions.edit', [
            'prescription' => $prescription->load(['items', 'patient', 'doctor.user', 'consultation']),
            ...$this->formData($this->clinicId()),
        ]);
    }

    public function update(UpdatePrescriptionRequest $request, Prescription $prescription): RedirectResponse
    {
        $this->authorizeClinic($prescription);
        $data = $this->prepareData($request->validated(), $this->clinicId());

        DB::transaction(function () use ($prescription, $data) {
            $prescription->update($data['prescription']);
            $prescription->items()->delete();
            $prescription->items()->createMany($data['items']);
        });

        return redirect()
            ->route('prescriptions.show', $prescription)
            ->with('success', 'Receta actualizada correctamente.');
    }

    public function destroy(Prescription $prescription): RedirectResponse
    {
        $this->authorizeClinic($prescription);
        $prescription->delete();

        return redirect()
            ->route('prescriptions.index')
            ->with('success', 'Receta eliminada correctamente.');
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
                'clinic_id' => 'Los datos seleccionados no pertenecen a la clínica del usuario autenticado.',
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
        abort_if(! $clinicId, 403, 'El usuario autenticado no tiene una clínica asignada.');

        return (int) $clinicId;
    }

    private function authorizeClinic(Prescription $prescription): void
    {
        abort_if((int) $prescription->patient?->clinic_id !== $this->clinicId(), 403);
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
