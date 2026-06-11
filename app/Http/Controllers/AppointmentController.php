<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAppointmentRequest;
use App\Http\Requests\UpdateAppointmentRequest;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AppointmentController extends Controller
{
    public function index(Request $request): View
    {
        $clinicId = $this->clinicId();
        $search = trim((string) $request->query('search'));
        $status = $request->query('status');
        $doctorId = $request->query('doctor_id');
        $date = $request->query('date');

        $appointments = Appointment::query()
            ->with(['patient', 'doctor.user', 'service'])
            ->where('clinic_id', $clinicId)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('reason', 'like', "%{$search}%")
                        ->orWhereHas('patient', function ($query) use ($search) {
                            $query->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('doctor.user', fn ($query) => $query->where('name', 'like', "%{$search}%"));
                });
            })
            ->when(in_array($status, ['scheduled', 'confirmed', 'completed', 'cancelled', 'no_show'], true), fn ($query) => $query->where('status', $status))
            ->when($doctorId, fn ($query) => $query->where('doctor_id', $doctorId))
            ->when($date, fn ($query) => $query->whereDate('appointment_date', $date))
            ->orderByDesc('appointment_date')
            ->orderBy('start_time')
            ->paginate(10)
            ->withQueryString();

        return view('appointments.index', [
            'appointments' => $appointments,
            'doctors' => $this->doctors($clinicId, onlyActive: false),
            'search' => $search,
            'status' => $status,
            'doctorId' => $doctorId,
            'date' => $date,
        ]);
    }

    public function create(): View
    {
        $clinicId = $this->clinicId();

        return view('appointments.create', $this->formData($clinicId));
    }

    public function store(StoreAppointmentRequest $request): RedirectResponse
    {
        $clinicId = $this->clinicId();
        $data = $this->prepareData($request->validated(), $clinicId);
        $this->ensureNoScheduleConflict($data, $clinicId);

        Appointment::create($data);

        return redirect()
            ->route('appointments.index')
            ->with('success', 'Cita creada correctamente.');
    }

    public function show(Appointment $appointment): View
    {
        $this->authorizeClinic($appointment);

        return view('appointments.show', [
            'appointment' => $appointment->load(['patient', 'doctor.user', 'doctor.specialty', 'service']),
        ]);
    }

    public function edit(Appointment $appointment): View
    {
        $this->authorizeClinic($appointment);
        $clinicId = $this->clinicId();

        return view('appointments.edit', [
            'appointment' => $appointment->load(['patient', 'doctor.user', 'service']),
            ...$this->formData($clinicId),
        ]);
    }

    public function update(UpdateAppointmentRequest $request, Appointment $appointment): RedirectResponse
    {
        $this->authorizeClinic($appointment);

        $clinicId = $this->clinicId();
        $data = $this->prepareData($request->validated(), $clinicId);
        $this->ensureNoScheduleConflict($data, $clinicId, $appointment);

        $appointment->update($data);

        return redirect()
            ->route('appointments.show', $appointment)
            ->with('success', 'Cita actualizada correctamente.');
    }

    public function destroy(Appointment $appointment): RedirectResponse
    {
        $this->authorizeClinic($appointment);
        $appointment->delete();

        return redirect()
            ->route('appointments.index')
            ->with('success', 'Cita eliminada correctamente.');
    }

    private function prepareData(array $validated, int $clinicId): array
    {
        $patient = Patient::where('clinic_id', $clinicId)->find($validated['patient_id']);
        $doctor = Doctor::where('clinic_id', $clinicId)->find($validated['doctor_id']);
        $service = isset($validated['service_id']) && $validated['service_id']
            ? Service::where('clinic_id', $clinicId)->find($validated['service_id'])
            : null;

        if (! $patient || ! $doctor || (($validated['service_id'] ?? null) && ! $service)) {
            throw ValidationException::withMessages([
                'clinic_id' => 'Los datos seleccionados no pertenecen a la clínica del usuario autenticado.',
            ]);
        }

        if (empty($validated['end_time']) && $service?->duration_minutes) {
            $validated['end_time'] = Carbon::createFromFormat('H:i', $validated['start_time'])
                ->addMinutes($service->duration_minutes)
                ->format('H:i');
        }

        return [
            ...$validated,
            'clinic_id' => $clinicId,
            'service_id' => $service?->id,
        ];
    }

    private function ensureNoScheduleConflict(array $data, int $clinicId, ?Appointment $ignore = null): void
    {
        $conflict = Appointment::query()
            ->where('clinic_id', $clinicId)
            ->where('doctor_id', $data['doctor_id'])
            ->whereDate('appointment_date', $data['appointment_date'])
            ->where('start_time', $data['start_time'])
            ->whereIn('status', ['scheduled', 'confirmed'])
            ->when($ignore, fn ($query) => $query->whereKeyNot($ignore->id))
            ->exists();

        if ($conflict) {
            throw ValidationException::withMessages([
                'start_time' => 'Ya existe una cita activa para este médico en la misma fecha y hora.',
            ]);
        }
    }

    private function clinicId(): int
    {
        $clinicId = auth()->user()?->clinic_id;
        abort_if(! $clinicId, 403, 'El usuario autenticado no tiene una clínica asignada.');

        return (int) $clinicId;
    }

    private function authorizeClinic(Appointment $appointment): void
    {
        abort_if($appointment->clinic_id !== $this->clinicId(), 403);
    }

    private function formData(int $clinicId): array
    {
        return [
            'patients' => Patient::where('clinic_id', $clinicId)->where('status', 'active')->orderBy('last_name')->orderBy('first_name')->get(),
            'doctors' => $this->doctors($clinicId),
            'services' => Service::where('clinic_id', $clinicId)->where('status', 'active')->orderBy('name')->get(),
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
