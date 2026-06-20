<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAppointmentRequest;
use App\Http\Requests\UpdateAppointmentRequest;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Payment;
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
        $doctor = $this->authenticatedDoctor();
        $isDoctorView = $this->isDoctorUser();

        $appointments = Appointment::query()
            ->with(['patient', 'doctor.user', 'service'])
            ->where('clinic_id', $clinicId)
            ->when($isDoctorView, function ($query) use ($doctor) {
                $doctor ? $query->where('doctor_id', $doctor->id) : $query->whereRaw('1 = 0');
            })
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
            ->when(! $isDoctorView && $doctorId, fn ($query) => $query->where('doctor_id', $doctorId))
            ->when($date, fn ($query) => $query->whereDate('appointment_date', $date))
            ->orderByDesc('appointment_date')
            ->orderBy('start_time')
            ->paginate(10)
            ->withQueryString();

        return view('appointments.index', [
            'appointments' => $appointments,
            'doctors' => $isDoctorView && $doctor ? collect([$doctor->loadMissing(['user', 'specialty'])]) : $this->doctors($clinicId, onlyActive: false),
            'search' => $search,
            'status' => $status,
            'doctorId' => $isDoctorView ? $doctor?->id : $doctorId,
            'date' => $date,
            'isDoctorView' => $isDoctorView,
        ]);
    }

    public function create(Request $request): View
    {
        $clinicId = $this->clinicId();

        return view('appointments.create', [
            ...$this->formData($clinicId),
            'prefillPatientId' => $request->query('patient_id'),
        ]);
    }

    public function store(StoreAppointmentRequest $request): RedirectResponse
    {
        $clinicId = $this->clinicId();
        $data = $this->prepareData($request->validated(), $clinicId);
        $this->ensureNoScheduleConflict($data, $clinicId);

        $appointment = Appointment::create($data);
        $this->syncPendingPayment($appointment);

        return redirect()
            ->route('appointments.index')
            ->with('success', 'Cita creada correctamente.');
    }

    public function show(Appointment $appointment): View
    {
        $this->authorizeAppointmentAccess($appointment);
        $appointment->load(['patient', 'doctor.user', 'doctor.specialty', 'service', 'consultation', 'payment']);

        return view('appointments.show', [
            'appointment' => $appointment,
            'canStartConsultation' => $this->canStartConsultation($appointment),
        ]);
    }

    public function edit(Appointment $appointment): View
    {
        $this->authorizeAppointmentAccess($appointment);
        $clinicId = $this->clinicId();

        return view('appointments.edit', [
            'appointment' => $appointment->load(['patient', 'doctor.user', 'service']),
            ...$this->formData($clinicId),
            'prefillPatientId' => null,
        ]);
    }

    public function update(UpdateAppointmentRequest $request, Appointment $appointment): RedirectResponse
    {
        $this->authorizeAppointmentAccess($appointment);

        $clinicId = $this->clinicId();
        $data = $this->prepareData($request->validated(), $clinicId);
        $this->ensureNoScheduleConflict($data, $clinicId, $appointment);

        $appointment->update($data);
        $this->syncPendingPayment($appointment->refresh());

        return redirect()
            ->route('appointments.show', $appointment)
            ->with('success', 'Cita actualizada correctamente.');
    }

    public function destroy(Appointment $appointment): RedirectResponse
    {
        $this->authorizeAppointmentAccess($appointment);
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

    private function authorizeAppointmentAccess(Appointment $appointment): void
    {
        abort_if((int) $appointment->clinic_id !== $this->clinicId(), 403);

        if ($this->isDoctorUser()) {
            $doctor = $this->authenticatedDoctor();
            abort_if(! $doctor || (int) $appointment->doctor_id !== (int) $doctor->id, 403);
        }
    }

    private function canStartConsultation(Appointment $appointment): bool
    {
        if (! auth()->user()?->can('consultations.create')) {
            return false;
        }

        if (in_array($appointment->status, ['cancelled', 'no_show'], true)) {
            return false;
        }

        if ($appointment->consultation) {
            return false;
        }

        if (! auth()->user()?->hasRole('administrador') && $appointment->payment?->payment_status !== 'paid') {
            return false;
        }

        if ($this->isDoctorUser()) {
            $doctor = $this->authenticatedDoctor();
            return $doctor && (int) $appointment->doctor_id === (int) $doctor->id;
        }

        return true;
    }

    private function syncPendingPayment(Appointment $appointment): void
    {
        $appointment->loadMissing(['doctor', 'service', 'payment']);
        $payment = $appointment->payment;

        if ($appointment->status === 'cancelled') {
            if ($payment && $payment->payment_status === 'pending') {
                $payment->update([
                    'payment_status' => 'cancelled',
                    'notes' => 'Pago cancelado automáticamente porque la cita fue cancelada.',
                ]);
            }

            return;
        }

        if ($payment && $payment->payment_status !== 'pending') {
            return;
        }

        $paymentData = $this->pendingPaymentData($appointment);

        Payment::updateOrCreate(
            ['appointment_id' => $appointment->id],
            $paymentData,
        );
    }

    private function pendingPaymentData(Appointment $appointment): array
    {
        $amount = (float) ($appointment->service?->price ?? 0);
        $notes = 'Pago generado automáticamente desde cita médica.';

        if ($amount <= 0) {
            $amount = (float) ($appointment->doctor?->consultation_fee ?? 0);
        }

        if ($amount <= 0) {
            $amount = 0;
            $notes .= ' Monto pendiente por definir.';
        }

        return [
            'clinic_id' => $appointment->clinic_id,
            'patient_id' => $appointment->patient_id,
            'appointment_id' => $appointment->id,
            'service_id' => $appointment->service_id,
            'amount' => $amount,
            'payment_method' => 'cash',
            'payment_status' => 'pending',
            'payment_date' => null,
            'notes' => $notes,
        ];
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