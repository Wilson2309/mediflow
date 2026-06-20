<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePatientRequest;
use App\Http\Requests\UpdatePatientRequest;
use App\Models\Doctor;
use App\Models\Patient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PatientController extends Controller
{
    public function index(Request $request): View
    {
        $clinicId = $this->clinicId();
        $search = trim((string) $request->query('search'));
        $status = $request->query('status');
        $doctor = $this->authenticatedDoctor();

        $patients = Patient::query()
            ->where('clinic_id', $clinicId)
            ->when($this->isDoctorUser(), function ($query) use ($doctor) {
                if (! $doctor) {
                    $query->whereRaw('1 = 0');
                    return;
                }

                $query->where(function ($query) use ($doctor) {
                    $query->whereHas('appointments', fn ($query) => $query->where('doctor_id', $doctor->id))
                        ->orWhereHas('consultations', fn ($query) => $query->where('doctor_id', $doctor->id));
                });
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('identification_number', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when(in_array($status, ['active', 'inactive'], true), fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('patients.index', [
            'patients' => $patients,
            'search' => $search,
            'status' => $status,
        ]);
    }

    public function create(): View
    {
        $this->clinicId();

        return view('patients.create');
    }

    public function store(StorePatientRequest $request): RedirectResponse
    {
        Patient::create([
            ...$request->validated(),
            'clinic_id' => $this->clinicId(),
        ]);

        return redirect()
            ->route('patients.index')
            ->with('success', 'Paciente creado correctamente.');
    }

    public function show(Patient $patient): View
    {
        $this->authorizePatientAccess($patient);

        return view('patients.show', [
            'patient' => $patient,
        ]);
    }

    public function edit(Patient $patient): View
    {
        $this->authorizePatientAccess($patient);

        return view('patients.edit', [
            'patient' => $patient,
        ]);
    }

    public function update(UpdatePatientRequest $request, Patient $patient): RedirectResponse
    {
        $this->authorizePatientAccess($patient);

        $patient->update($request->validated());

        return redirect()
            ->route('patients.show', $patient)
            ->with('success', 'Paciente actualizado correctamente.');
    }

    public function destroy(Patient $patient): RedirectResponse
    {
        $this->authorizePatientAccess($patient);

        $patient->delete();

        return redirect()
            ->route('patients.index')
            ->with('success', 'Paciente eliminado correctamente.');
    }

    private function clinicId(): int
    {
        $clinicId = auth()->user()?->clinic_id;

        abort_if(! $clinicId, 403, 'El usuario autenticado no tiene una clínica asignada.');

        return (int) $clinicId;
    }

    private function authorizePatientAccess(Patient $patient): void
    {
        abort_if((int) $patient->clinic_id !== $this->clinicId(), 403);

        if ($this->isDoctorUser()) {
            $doctor = $this->authenticatedDoctor();
            abort_if(! $doctor || ! $this->patientBelongsToDoctor($patient, $doctor), 403);
        }
    }

    private function patientBelongsToDoctor(Patient $patient, Doctor $doctor): bool
    {
        return $patient->appointments()->where('doctor_id', $doctor->id)->exists()
            || $patient->consultations()->where('doctor_id', $doctor->id)->exists();
    }

    private function isDoctorUser(): bool
    {
        return (bool) auth()->user()?->hasRole('medico');
    }

    private function authenticatedDoctor(): ?Doctor
    {
        $user = auth()->user();

        if (! $user?->id) {
            return null;
        }

        return Doctor::where('clinic_id', $this->clinicId())
            ->where('user_id', $user->id)
            ->first();
    }
}