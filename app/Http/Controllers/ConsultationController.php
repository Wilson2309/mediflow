<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreConsultationRequest;
use App\Http\Requests\UpdateConsultationRequest;
use App\Models\Appointment;
use App\Models\Consultation;
use App\Models\Doctor;
use App\Models\Patient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ConsultationController extends Controller
{
    public function index(Request $request): View
    {
        $clinicId = $this->clinicId();
        $search = trim((string) $request->query('search'));
        $doctorId = $request->query('doctor_id');
        $date = $request->query('date');
        $doctor = $this->authenticatedDoctor();
        $isDoctorView = $this->isDoctorUser();

        $consultations = Consultation::query()
            ->with(['patient', 'doctor.user', 'appointment'])
            ->whereHas('patient', fn ($query) => $query->where('clinic_id', $clinicId))
            ->when($isDoctorView, function ($query) use ($doctor) {
                $doctor ? $query->where('doctor_id', $doctor->id) : $query->whereRaw('1 = 0');
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('reason', 'like', "%{$search}%")
                        ->orWhere('diagnosis', 'like', "%{$search}%")
                        ->orWhereHas('patient', function ($query) use ($search) {
                            $query->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('doctor.user', fn ($query) => $query->where('name', 'like', "%{$search}%"));
                });
            })
            ->when(! $isDoctorView && $doctorId, fn ($query) => $query->where('doctor_id', $doctorId))
            ->when($date, fn ($query) => $query->whereDate('consultation_date', $date))
            ->orderByDesc('consultation_date')
            ->paginate(10)
            ->withQueryString();

        return view('consultations.index', [
            'consultations' => $consultations,
            'doctors' => $isDoctorView && $doctor ? collect([$doctor->loadMissing(['user', 'specialty'])]) : $this->doctors($clinicId, onlyActive: false),
            'search' => $search,
            'doctorId' => $isDoctorView ? $doctor?->id : $doctorId,
            'date' => $date,
        ]);
    }

    public function create(Request $request): View
    {
        $clinicId = $this->clinicId();
        $appointment = $request->query('appointment_id')
            ? $this->appointmentForPrefill((int) $request->query('appointment_id'), $clinicId)
            : null;

        return view('consultations.create', $this->formData($clinicId, appointment: $appointment));
    }

    public function store(StoreConsultationRequest $request): RedirectResponse
    {
        $clinicId = $this->clinicId();
        $data = $this->prepareData($request->validated(), $clinicId);

        $consultation = Consultation::create($data);
        $this->markAppointmentAsCompleted($consultation);

        return redirect()
            ->route('consultations.index')
            ->with('success', 'Consulta creada correctamente.');
    }

    public function show(Consultation $consultation): View
    {
        $this->authorizeClinic($consultation);

        return view('consultations.show', [
            'consultation' => $consultation->load(['appointment.service', 'patient', 'doctor.user', 'doctor.specialty']),
        ]);
    }

    public function edit(Consultation $consultation): View
    {
        $this->authorizeClinic($consultation);

        return view('consultations.edit', [
            'consultation' => $consultation->load(['appointment', 'patient', 'doctor.user']),
            ...$this->formData($this->clinicId(), $consultation),
        ]);
    }

    public function update(UpdateConsultationRequest $request, Consultation $consultation): RedirectResponse
    {
        $this->authorizeClinic($consultation);

        $data = $this->prepareData($request->validated(), $this->clinicId(), $consultation);
        $consultation->update($data);
        $this->markAppointmentAsCompleted($consultation->refresh());

        return redirect()
            ->route('consultations.show', $consultation)
            ->with('success', 'Consulta actualizada correctamente.');
    }

    public function destroy(Consultation $consultation): RedirectResponse
    {
        $this->authorizeClinic($consultation);
        $consultation->delete();

        return redirect()
            ->route('consultations.index')
            ->with('success', 'Consulta eliminada correctamente.');
    }

    private function prepareData(array $validated, int $clinicId, ?Consultation $ignore = null): array
    {
        $patient = Patient::where('clinic_id', $clinicId)->find($validated['patient_id']);
        $doctor = Doctor::where('clinic_id', $clinicId)->find($validated['doctor_id']);
        $appointment = isset($validated['appointment_id']) && $validated['appointment_id']
            ? Appointment::where('clinic_id', $clinicId)->find($validated['appointment_id'])
            : null;

        if (! $patient || ! $doctor || (($validated['appointment_id'] ?? null) && ! $appointment)) {
            throw ValidationException::withMessages([
                'clinic_id' => 'Los datos seleccionados no pertenecen a la clínica del usuario autenticado.',
            ]);
        }

        if ($this->isDoctorUser()) {
            $authenticatedDoctor = $this->authenticatedDoctor();

            if (! $authenticatedDoctor || (int) $doctor->id !== (int) $authenticatedDoctor->id) {
                throw ValidationException::withMessages([
                    'doctor_id' => 'No puede registrar consultas para otro médico.',
                ]);
            }
        }

        if ($appointment) {
            if (in_array($appointment->status, ['cancelled', 'no_show'], true)) {
                throw ValidationException::withMessages([
                    'appointment_id' => 'No se puede registrar una consulta para una cita cancelada o marcada como no asistió.',
                ]);
            }

            $alreadyUsed = Consultation::where('appointment_id', $appointment->id)
                ->when($ignore, fn ($query) => $query->whereKeyNot($ignore->id))
                ->exists();

            if ($alreadyUsed) {
                throw ValidationException::withMessages([
                    'appointment_id' => 'La cita seleccionada ya tiene una consulta registrada.',
                ]);
            }

            if ((int) $appointment->patient_id !== (int) $patient->id || (int) $appointment->doctor_id !== (int) $doctor->id) {
                throw ValidationException::withMessages([
                    'appointment_id' => 'La cita seleccionada no coincide con el paciente y médico indicados.',
                ]);
            }
        }

        return [
            ...$validated,
            'appointment_id' => $appointment?->id,
        ];
    }

    private function markAppointmentAsCompleted(Consultation $consultation): void
    {
        $appointment = $consultation->appointment;

        if ($appointment && in_array($appointment->status, ['scheduled', 'confirmed'], true)) {
            $appointment->update(['status' => 'completed']);
        }
    }

    private function clinicId(): int
    {
        $clinicId = auth()->user()?->clinic_id;
        abort_if(! $clinicId, 403, 'El usuario autenticado no tiene una clínica asignada.');

        return (int) $clinicId;
    }

    private function authorizeClinic(Consultation $consultation): void
    {
        abort_if((int) $consultation->patient?->clinic_id !== $this->clinicId(), 403);

        if ($this->isDoctorUser()) {
            $doctor = $this->authenticatedDoctor();
            abort_if(! $doctor || (int) $consultation->doctor_id !== (int) $doctor->id, 403);
        }
    }

    private function formData(int $clinicId, ?Consultation $consultation = null, ?Appointment $appointment = null): array
    {
        $doctor = $this->authenticatedDoctor();
        $isDoctorView = $this->isDoctorUser();

        return [
            'appointments' => $this->availableAppointments($clinicId, $consultation, $appointment),
            'patients' => Patient::where('clinic_id', $clinicId)
                ->where('status', 'active')
                ->when($isDoctorView, function ($query) use ($doctor) {
                    if (! $doctor) {
                        $query->whereRaw('1 = 0');
                        return;
                    }

                    $query->where(function ($query) use ($doctor) {
                        $query->whereHas('appointments', fn ($query) => $query->where('doctor_id', $doctor->id))
                            ->orWhereHas('consultations', fn ($query) => $query->where('doctor_id', $doctor->id));
                    });
                })
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get(),
            'doctors' => $isDoctorView && $doctor ? collect([$doctor->loadMissing(['user', 'specialty'])]) : $this->doctors($clinicId),
            'prefill' => $this->prefillFromAppointment($appointment),
        ];
    }

    private function availableAppointments(int $clinicId, ?Consultation $consultation = null, ?Appointment $selectedAppointment = null)
    {
        $doctor = $this->authenticatedDoctor();

        return Appointment::with(['patient', 'doctor.user', 'service'])
            ->where('clinic_id', $clinicId)
            ->when($this->isDoctorUser(), function ($query) use ($doctor) {
                $doctor ? $query->where('doctor_id', $doctor->id) : $query->whereRaw('1 = 0');
            })
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->where(function ($query) use ($consultation, $selectedAppointment) {
                $query->doesntHave('consultation')
                    ->when($consultation?->appointment_id, fn ($query) => $query->orWhere('id', $consultation->appointment_id))
                    ->when($selectedAppointment?->id, fn ($query) => $query->orWhere('id', $selectedAppointment->id));
            })
            ->orderByDesc('appointment_date')
            ->orderBy('start_time')
            ->get();
    }

    private function appointmentForPrefill(int $appointmentId, int $clinicId): Appointment
    {
        $appointment = Appointment::with(['patient', 'doctor.user'])
            ->where('clinic_id', $clinicId)
            ->findOrFail($appointmentId);

        if ($this->isDoctorUser()) {
            $doctor = $this->authenticatedDoctor();
            abort_if(! $doctor || (int) $appointment->doctor_id !== (int) $doctor->id, 403);
        }

        abort_if(in_array($appointment->status, ['cancelled', 'no_show'], true), 403);
        abort_if($appointment->consultation()->exists(), 403);

        return $appointment;
    }

    private function prefillFromAppointment(?Appointment $appointment): array
    {
        if (! $appointment) {
            return [];
        }

        $time = substr((string) $appointment->start_time, 0, 5) ?: '00:00';

        return [
            'appointment_id' => $appointment->id,
            'patient_id' => $appointment->patient_id,
            'doctor_id' => $appointment->doctor_id,
            'reason' => $appointment->reason,
            'consultation_date' => $appointment->appointment_date?->format('Y-m-d').'T'.$time,
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