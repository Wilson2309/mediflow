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

        $consultations = Consultation::query()
            ->with(['patient', 'doctor.user', 'appointment'])
            ->whereHas('patient', fn ($query) => $query->where('clinic_id', $clinicId))
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
            ->when($doctorId, fn ($query) => $query->where('doctor_id', $doctorId))
            ->when($date, fn ($query) => $query->whereDate('consultation_date', $date))
            ->orderByDesc('consultation_date')
            ->paginate(10)
            ->withQueryString();

        return view('consultations.index', [
            'consultations' => $consultations,
            'doctors' => $this->doctors($clinicId, onlyActive: false),
            'search' => $search,
            'doctorId' => $doctorId,
            'date' => $date,
        ]);
    }

    public function create(): View
    {
        return view('consultations.create', $this->formData($this->clinicId()));
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
    }

    private function formData(int $clinicId, ?Consultation $consultation = null): array
    {
        return [
            'appointments' => $this->availableAppointments($clinicId, $consultation),
            'patients' => Patient::where('clinic_id', $clinicId)->where('status', 'active')->orderBy('last_name')->orderBy('first_name')->get(),
            'doctors' => $this->doctors($clinicId),
        ];
    }

    private function availableAppointments(int $clinicId, ?Consultation $consultation = null)
    {
        return Appointment::with(['patient', 'doctor.user', 'service'])
            ->where('clinic_id', $clinicId)
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->where(function ($query) use ($consultation) {
                $query->doesntHave('consultation')
                    ->when($consultation?->appointment_id, fn ($query) => $query->orWhereKey($consultation->appointment_id));
            })
            ->orderByDesc('appointment_date')
            ->orderBy('start_time')
            ->get();
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
