<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMedicalRecordRequest;
use App\Http\Requests\UpdateMedicalRecordRequest;
use App\Models\MedicalRecord;
use App\Models\Patient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MedicalRecordController extends Controller
{
    public function index(Request $request): View
    {
        $clinicId = $this->clinicId();
        $search = trim((string) $request->query('search'));

        $medicalRecords = MedicalRecord::query()
            ->with('patient')
            ->whereHas('patient', fn ($query) => $query->where('clinic_id', $clinicId))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('personal_history', 'like', "%{$search}%")
                        ->orWhere('chronic_diseases', 'like', "%{$search}%")
                        ->orWhere('current_medications', 'like', "%{$search}%")
                        ->orWhereHas('patient', function ($query) use ($search) {
                            $query->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhere('identification_number', 'like', "%{$search}%");
                        });
                });
            })
            ->orderByDesc('updated_at')
            ->paginate(10)
            ->withQueryString();

        return view('medical-records.index', [
            'medicalRecords' => $medicalRecords,
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        return view('medical-records.create', [
            'patients' => $this->availablePatientsForCreate($this->clinicId()),
        ]);
    }

    public function store(StoreMedicalRecordRequest $request): RedirectResponse
    {
        $data = $this->prepareData($request->validated(), $this->clinicId());

        $medicalRecord = MedicalRecord::create($data);

        return redirect()
            ->route('medical-records.show', $medicalRecord)
            ->with('success', 'Historial clinico creado correctamente.');
    }

    public function show(MedicalRecord $medical_record): View
    {
        $this->authorizeClinic($medical_record);
        $medicalRecord = $medical_record->load('patient');
        $patient = $medicalRecord->patient;

        return view('medical-records.show', [
            'medicalRecord' => $medicalRecord,
            'patient' => $patient,
            'recentConsultations' => $patient->consultations()
                ->with('doctor.user')
                ->orderByDesc('consultation_date')
                ->limit(5)
                ->get(),
            'recentPrescriptions' => $patient->prescriptions()
                ->with(['doctor.user', 'items'])
                ->orderByDesc('prescription_date')
                ->limit(5)
                ->get(),
            'recentAppointments' => $patient->appointments()
                ->with('doctor.user')
                ->orderByDesc('appointment_date')
                ->orderByDesc('start_time')
                ->limit(5)
                ->get(),
        ]);
    }

    public function edit(MedicalRecord $medical_record): View
    {
        $this->authorizeClinic($medical_record);

        return view('medical-records.edit', [
            'medicalRecord' => $medical_record->load('patient'),
            'patients' => $this->availablePatientsForEdit($this->clinicId(), $medical_record),
        ]);
    }

    public function update(UpdateMedicalRecordRequest $request, MedicalRecord $medical_record): RedirectResponse
    {
        $this->authorizeClinic($medical_record);

        $medical_record->update($this->prepareData($request->validated(), $this->clinicId(), $medical_record));

        return redirect()
            ->route('medical-records.show', $medical_record)
            ->with('success', 'Historial clinico actualizado correctamente.');
    }

    public function destroy(MedicalRecord $medical_record): RedirectResponse
    {
        $this->authorizeClinic($medical_record);
        $medical_record->delete();

        return redirect()
            ->route('medical-records.index')
            ->with('success', 'Historial clinico eliminado correctamente.');
    }

    private function prepareData(array $validated, int $clinicId, ?MedicalRecord $ignore = null): array
    {
        $patient = Patient::where('clinic_id', $clinicId)->find($validated['patient_id']);

        if (! $patient) {
            throw ValidationException::withMessages([
                'clinic_id' => 'El paciente seleccionado no pertenece a la clinica del usuario autenticado.',
            ]);
        }

        $alreadyHasRecord = MedicalRecord::where('patient_id', $patient->id)
            ->when($ignore, fn ($query) => $query->whereKeyNot($ignore->id))
            ->exists();

        if ($alreadyHasRecord) {
            throw ValidationException::withMessages([
                'patient_id' => 'El paciente seleccionado ya tiene un historial clinico registrado.',
            ]);
        }

        return [
            'patient_id' => $patient->id,
            'personal_history' => $validated['personal_history'] ?? null,
            'family_history' => $validated['family_history'] ?? null,
            'surgical_history' => $validated['surgical_history'] ?? null,
            'current_medications' => $validated['current_medications'] ?? null,
            'chronic_diseases' => $validated['chronic_diseases'] ?? null,
            'observations' => $validated['observations'] ?? null,
        ];
    }

    private function clinicId(): int
    {
        $clinicId = auth()->user()?->clinic_id;
        abort_if(! $clinicId, 403, 'El usuario autenticado no tiene una clinica asignada.');

        return (int) $clinicId;
    }

    private function authorizeClinic(MedicalRecord $medicalRecord): void
    {
        abort_if((int) $medicalRecord->patient?->clinic_id !== $this->clinicId(), 403);
    }

    private function availablePatientsForCreate(int $clinicId)
    {
        return Patient::where('clinic_id', $clinicId)
            ->where('status', 'active')
            ->doesntHave('medicalRecord')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    private function availablePatientsForEdit(int $clinicId, MedicalRecord $medicalRecord)
    {
        return Patient::where('clinic_id', $clinicId)
            ->where('status', 'active')
            ->where(function ($query) use ($medicalRecord) {
                $query->doesntHave('medicalRecord')
                    ->orWhere('id', $medicalRecord->patient_id);
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }
}
