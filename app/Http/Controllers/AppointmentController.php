<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAppointmentRequest;
use App\Http\Requests\UpdateAppointmentRequest;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Service;
use App\Services\AuditLogger;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AppointmentController extends Controller
{
    private const ACTIVE_BLOCKING_STATUSES = ['scheduled', 'confirmed'];
    private const DEFAULT_APPOINTMENT_DURATION = 30;
    private const SLOT_START = '08:00';
    private const SLOT_END = '17:00';
    private const SLOT_STEP_MINUTES = 15;

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
            ...$this->formData($clinicId, prefillPatientId: $request->query('patient_id')),
            'prefillPatientId' => $request->query('patient_id'),
        ]);
    }

    public function store(StoreAppointmentRequest $request): RedirectResponse
    {
        $clinicId = $this->clinicId();
        $data = $this->prepareData($request->validated(), $clinicId);
        $this->ensureDoctorCanProvideService($data, $clinicId);
        $this->ensureNoScheduleConflict($data, $clinicId);

        $appointment = Appointment::create($data);
        AuditLogger::log('appointment.created', 'appointments', $appointment, [], AuditLogger::modelSnapshot($appointment), 'Cita medica creada.');
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
            'appointment' => $appointment->load(['patient', 'doctor.user', 'doctor.specialty', 'service']),
            ...$this->formData($clinicId, appointment: $appointment),
            'prefillPatientId' => null,
        ]);
    }

    public function update(UpdateAppointmentRequest $request, Appointment $appointment): RedirectResponse
    {
        $this->authorizeAppointmentAccess($appointment);

        $clinicId = $this->clinicId();
        $data = $this->prepareData($request->validated(), $clinicId);
        $this->ensureDoctorCanProvideService($data, $clinicId, $appointment);
        $this->ensureNoScheduleConflict($data, $clinicId, $appointment);

        $old = AuditLogger::modelSnapshot($appointment);
        $oldStatus = $appointment->status;
        $appointment->update($data);
        $appointment->refresh();

        AuditLogger::log(
            $this->appointmentAction($appointment, $oldStatus),
            'appointments',
            $appointment,
            $old,
            AuditLogger::modelSnapshot($appointment),
            'Cita medica actualizada.'
        );
        $this->syncPendingPayment($appointment);

        return redirect()
            ->route('appointments.show', $appointment)
            ->with('success', 'Cita actualizada correctamente.');
    }

    public function destroy(Appointment $appointment): RedirectResponse
    {
        $this->authorizeAppointmentAccess($appointment);
        $old = AuditLogger::modelSnapshot($appointment);
        AuditLogger::log('appointment.deleted', 'appointments', $appointment, $old, [], 'Cita medica eliminada.');

        $appointment->delete();

        return redirect()
            ->route('appointments.index')
            ->with('success', 'Cita eliminada correctamente.');
    }

    public function searchPatients(Request $request): JsonResponse
    {
        $clinicId = $this->clinicId();
        $term = trim((string) $request->query('q'));

        $patients = Patient::query()
            ->where('clinic_id', $clinicId)
            ->where('status', 'active')
            ->when($term !== '', function ($query) use ($term) {
                $query->where(function ($query) use ($term) {
                    $query->where('first_name', 'like', "%{$term}%")
                        ->orWhere('last_name', 'like', "%{$term}%")
                        ->orWhere('identification_number', 'like', "%{$term}%")
                        ->orWhere('phone', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%");
                });
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit(12)
            ->get()
            ->map(fn (Patient $patient) => [
                'id' => $patient->id,
                'label' => trim($patient->full_name),
                'identification' => $patient->identification_number,
                'contact' => $patient->phone ?: $patient->email,
            ]);

        return response()->json($patients);
    }

    public function searchDoctors(Request $request): JsonResponse
    {
        $clinicId = $this->clinicId();
        $term = trim((string) $request->query('q'));
        $serviceId = $request->integer('service_id') ?: null;

        if ($serviceId && ! Service::where('clinic_id', $clinicId)->whereKey($serviceId)->exists()) {
            abort(404);
        }

        $doctors = Doctor::query()
            ->with(['user', 'specialty'])
            ->where('clinic_id', $clinicId)
            ->where('status', 'active')
            ->when($serviceId, fn ($query) => $query->whereHas('services', fn ($query) => $query->whereKey($serviceId)))
            ->when($term !== '', function ($query) use ($term) {
                $query->where(function ($query) use ($term) {
                    $query->where('license_number', 'like', "%{$term}%")
                        ->orWhereHas('user', fn ($query) => $query->where('name', 'like', "%{$term}%"))
                        ->orWhereHas('specialty', fn ($query) => $query->where('name', 'like', "%{$term}%"));
                });
            })
            ->limit(12)
            ->get()
            ->sortBy(fn (Doctor $doctor) => $doctor->user?->name ?? '')
            ->values()
            ->map(fn (Doctor $doctor) => [
                'id' => $doctor->id,
                'label' => $doctor->user?->name ?? 'Medico sin usuario',
                'specialty' => $doctor->specialty?->name,
                'license' => $doctor->license_number,
            ]);

        return response()->json($doctors);
    }

    public function availability(Request $request): JsonResponse
    {
        $clinicId = $this->clinicId();
        $doctorId = $request->integer('doctor_id');
        $serviceId = $request->integer('service_id') ?: null;
        $date = (string) $request->query('date');
        $ignoreId = $request->integer('appointment_id') ?: null;

        $doctor = Doctor::where('clinic_id', $clinicId)->where('status', 'active')->findOrFail($doctorId);
        $service = $serviceId ? Service::where('clinic_id', $clinicId)->where('status', 'active')->findOrFail($serviceId) : null;

        if ($service && ! $this->doctorProvidesService($doctor, $service)) {
            return response()->json([
                'available_slots' => [],
                'unavailable_slots' => [],
                'duration' => $this->appointmentDuration($service),
                'message' => 'Este medico no ofrece el servicio seleccionado.',
            ], 422);
        }

        try {
            $day = Carbon::createFromFormat('Y-m-d', $date)->startOfDay();
        } catch (\Throwable) {
            return response()->json(['message' => 'Fecha invalida.'], 422);
        }

        $duration = $this->appointmentDuration($service);
        $available = [];
        $unavailable = [];
        $cursor = $day->copy()->setTimeFromTimeString(self::SLOT_START);
        $endOfDay = $day->copy()->setTimeFromTimeString(self::SLOT_END);

        while ($cursor->copy()->addMinutes($duration)->lessThanOrEqualTo($endOfDay)) {
            $slot = $cursor->format('H:i');
            $slotData = [
                'doctor_id' => $doctor->id,
                'service_id' => $service?->id,
                'appointment_date' => $day->format('Y-m-d'),
                'start_time' => $slot,
                'end_time' => $cursor->copy()->addMinutes($duration)->format('H:i'),
            ];

            if ($this->hasScheduleConflict($slotData, $clinicId, $ignoreId)) {
                $unavailable[] = $slot;
            } else {
                $available[] = $slot;
            }

            $cursor->addMinutes(self::SLOT_STEP_MINUTES);
        }

        return response()->json([
            'available_slots' => $available,
            'unavailable_slots' => $unavailable,
            'duration' => $duration,
            'message' => $available === [] ? 'No hay horarios disponibles para este medico en la fecha seleccionada.' : null,
        ]);
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
                'clinic_id' => 'Los datos seleccionados no pertenecen a la clinica del usuario autenticado.',
            ]);
        }

        $duration = $this->appointmentDuration($service);

        if (empty($validated['end_time'])) {
            $validated['end_time'] = Carbon::createFromFormat('H:i', $validated['start_time'])
                ->addMinutes($duration)
                ->format('H:i');
        }

        return [
            ...$validated,
            'clinic_id' => $clinicId,
            'service_id' => $service?->id,
        ];
    }

    private function ensureDoctorCanProvideService(array $data, int $clinicId, ?Appointment $appointment = null): void
    {
        if (empty($data['service_id'])) {
            return;
        }

        $doctor = Doctor::where('clinic_id', $clinicId)->find($data['doctor_id']);
        $service = Service::where('clinic_id', $clinicId)->find($data['service_id']);

        if (! $doctor || ! $service || $this->doctorProvidesService($doctor, $service)) {
            return;
        }

        AuditLogger::log('appointment.doctor_service_mismatch', 'appointments', $appointment, [], [
            'doctor_id' => $doctor->id,
            'service_id' => $service->id,
        ], 'Intento de asignar un medico incompatible con el servicio seleccionado.');

        throw ValidationException::withMessages([
            'doctor_id' => 'Este medico no ofrece el servicio seleccionado.',
        ]);
    }

    private function ensureNoScheduleConflict(array $data, int $clinicId, ?Appointment $ignore = null): void
    {
        if (! $this->hasScheduleConflict($data, $clinicId, $ignore?->id)) {
            return;
        }

        AuditLogger::log('appointment.availability_rejected', 'appointments', $ignore, [], [
            'doctor_id' => $data['doctor_id'] ?? null,
            'appointment_date' => $data['appointment_date'] ?? null,
            'start_time' => $data['start_time'] ?? null,
            'end_time' => $data['end_time'] ?? null,
        ], 'Intento de agendar una cita en un horario ocupado.');

        throw ValidationException::withMessages([
            'start_time' => 'El medico ya tiene una cita programada en esa hora.',
        ]);
    }

    private function hasScheduleConflict(array $data, int $clinicId, ?int $ignoreId = null): bool
    {
        $start = $this->timeOnDate($data['appointment_date'], $data['start_time']);
        $endTime = $data['end_time'] ?? null;
        $service = ! empty($data['service_id']) ? Service::where('clinic_id', $clinicId)->find($data['service_id']) : null;
        $end = $endTime
            ? $this->timeOnDate($data['appointment_date'], $endTime)
            : $start->copy()->addMinutes($this->appointmentDuration($service));

        return Appointment::query()
            ->with('service')
            ->where('clinic_id', $clinicId)
            ->where('doctor_id', $data['doctor_id'])
            ->whereDate('appointment_date', $data['appointment_date'])
            ->whereIn('status', self::ACTIVE_BLOCKING_STATUSES)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->get()
            ->contains(function (Appointment $appointment) use ($start, $end) {
                $existingStart = $this->timeOnDate($appointment->appointment_date->format('Y-m-d'), substr((string) $appointment->start_time, 0, 5));
                $existingEnd = $appointment->end_time
                    ? $this->timeOnDate($appointment->appointment_date->format('Y-m-d'), substr((string) $appointment->end_time, 0, 5))
                    : $existingStart->copy()->addMinutes($this->appointmentDuration($appointment->service));

                return $existingStart->lt($end) && $existingEnd->gt($start);
            });
    }

    private function doctorProvidesService(Doctor $doctor, Service $service): bool
    {
        return $doctor->services()->whereKey($service->id)->exists();
    }

    private function appointmentDuration(?Service $service): int
    {
        return max((int) ($service?->duration_minutes ?: self::DEFAULT_APPOINTMENT_DURATION), 1);
    }

    private function timeOnDate(string $date, string $time): Carbon
    {
        return Carbon::createFromFormat('Y-m-d H:i', $date.' '.substr($time, 0, 5));
    }

    private function clinicId(): int
    {
        $clinicId = auth()->user()?->clinic_id;
        abort_if(! $clinicId, 403, 'El usuario autenticado no tiene una clinica asignada.');

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
                $old = AuditLogger::modelSnapshot($payment);
                $payment->update([
                    'payment_status' => 'cancelled',
                    'notes' => 'Pago cancelado automaticamente porque la cita fue cancelada.',
                ]);
                AuditLogger::log('payment.cancelled', 'payments', $payment, $old, AuditLogger::modelSnapshot($payment), 'Pago pendiente cancelado automaticamente desde cita medica.');
            }

            return;
        }

        if ($payment && $payment->payment_status !== 'pending') {
            return;
        }

        $paymentData = $this->pendingPaymentData($appointment);

        $old = $payment ? AuditLogger::modelSnapshot($payment) : [];
        $syncedPayment = Payment::updateOrCreate(
            ['appointment_id' => $appointment->id],
            $paymentData,
        );

        AuditLogger::log($syncedPayment->wasRecentlyCreated ? 'payment.pending_created' : 'payment.updated', 'payments', $syncedPayment, $old, AuditLogger::modelSnapshot($syncedPayment), $syncedPayment->wasRecentlyCreated ? 'Pago pendiente generado automaticamente desde cita medica.' : 'Pago pendiente actualizado automaticamente desde cita medica.');
    }

    private function appointmentAction(Appointment $appointment, ?string $oldStatus): string
    {
        if ($appointment->status !== $oldStatus && in_array($appointment->status, ['cancelled', 'confirmed', 'completed', 'no_show'], true)) {
            return 'appointment.'.$appointment->status;
        }

        return 'appointment.updated';
    }

    private function pendingPaymentData(Appointment $appointment): array
    {
        $amount = (float) ($appointment->service?->price ?? 0);
        $notes = 'Pago generado automaticamente desde cita medica.';

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

    private function formData(int $clinicId, ?Appointment $appointment = null, mixed $prefillPatientId = null): array
    {
        $selectedPatientId = old('patient_id', $appointment?->patient_id ?? $prefillPatientId);
        $selectedDoctorId = old('doctor_id', $appointment?->doctor_id);

        return [
            'selectedPatient' => $selectedPatientId ? Patient::where('clinic_id', $clinicId)->find($selectedPatientId) : null,
            'selectedDoctor' => $selectedDoctorId ? Doctor::with(['user', 'specialty'])->where('clinic_id', $clinicId)->find($selectedDoctorId) : null,
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
