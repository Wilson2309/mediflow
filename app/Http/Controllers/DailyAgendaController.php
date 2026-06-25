<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Doctor;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DailyAgendaController extends Controller
{
    public function index(Request $request): View
    {
        $clinicId = $this->clinicId();
        $user = $request->user();
        $doctor = $this->authenticatedDoctor();
        $isDoctorView = (bool) $user?->hasRole('medico');
        $date = $this->selectedDate($request);
        $search = trim((string) $request->query('search'));
        $appointmentStatus = $request->query('status');
        $paymentStatus = $request->query('payment_status');
        $doctorId = $request->query('doctor_id');

        $agendaQuery = Appointment::query()
            ->with(['patient', 'doctor.user', 'doctor.specialty', 'service', 'payment', 'consultation'])
            ->where('clinic_id', $clinicId)
            ->whereDate('appointment_date', $date)
            ->when($isDoctorView, function ($query) use ($doctor) {
                $doctor ? $query->where('doctor_id', $doctor->id) : $query->whereRaw('1 = 0');
            })
            ->when(! $isDoctorView && $doctorId, fn ($query) => $query->where('doctor_id', $doctorId))
            ->when(in_array($appointmentStatus, $this->appointmentStatuses(), true), fn ($query) => $query->where('status', $appointmentStatus))
            ->when(in_array($paymentStatus, $this->paymentStatuses(), true), function ($query) use ($paymentStatus) {
                if ($paymentStatus === 'without_payment') {
                    $query->doesntHave('payment');

                    return;
                }

                $query->whereHas('payment', fn ($query) => $query->where('payment_status', $paymentStatus));
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('reason', 'like', "%{$search}%")
                        ->orWhereHas('patient', function ($query) use ($search) {
                            $query->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhere('identification_number', 'like', "%{$search}%");
                        })
                        ->orWhereHas('doctor.user', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('service', fn ($query) => $query->where('name', 'like', "%{$search}%"));
                });
            });

        $summaryQuery = clone $agendaQuery;
        $appointments = $agendaQuery
            ->orderBy('start_time')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        return view('daily-agenda.index', [
            'appointments' => $appointments,
            'doctors' => $isDoctorView && $doctor ? collect([$doctor->loadMissing(['user', 'specialty'])]) : $this->doctors($clinicId),
            'selectedDate' => $date,
            'search' => $search,
            'status' => $appointmentStatus,
            'paymentStatus' => $paymentStatus,
            'doctorId' => $isDoctorView ? $doctor?->id : $doctorId,
            'isDoctorView' => $isDoctorView,
            'summary' => [
                'total' => (clone $summaryQuery)->count(),
                'pending_payments' => (clone $summaryQuery)->whereHas('payment', fn ($query) => $query->where('payment_status', 'pending'))->count(),
                'paid' => (clone $summaryQuery)->whereHas('payment', fn ($query) => $query->where('payment_status', 'paid'))->count(),
                'completed' => (clone $summaryQuery)->where('status', 'completed')->count(),
                'cancelled_or_no_show' => (clone $summaryQuery)->whereIn('status', ['cancelled', 'no_show'])->count(),
                'income' => $user?->can('payments.view') ? $this->incomeForDate($clinicId, $date, $isDoctorView, $doctor) : 0,
            ],
        ]);
    }

    public function cancel(Appointment $appointment, Request $request): RedirectResponse
    {
        $this->authorizeStatusChange($appointment);

        if (! in_array($appointment->status, ['cancelled', 'completed'], true)) {
            $appointment->update(['status' => 'cancelled']);
            $this->cancelPendingPayment($appointment);
            $this->audit('appointment.cancelled', $appointment, $request);
        }

        return redirect()
            ->route('daily-agenda.index', ['date' => $appointment->appointment_date?->toDateString()])
            ->with('success', 'Cita cancelada desde agenda.');
    }

    public function markNoShow(Appointment $appointment, Request $request): RedirectResponse
    {
        $this->authorizeStatusChange($appointment);

        if (! in_array($appointment->status, ['cancelled', 'completed'], true)) {
            $appointment->update(['status' => 'no_show']);
            $this->audit('appointment.marked_no_show', $appointment, $request);
        }

        return redirect()
            ->route('daily-agenda.index', ['date' => $appointment->appointment_date?->toDateString()])
            ->with('success', 'Cita marcada como no asistio.');
    }

    private function selectedDate(Request $request): string
    {
        $date = (string) $request->query('date');

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1) {
            return $date;
        }

        return today()->toDateString();
    }

    private function incomeForDate(int $clinicId, string $date, bool $isDoctorView, ?Doctor $doctor): float
    {
        return (float) Payment::where('clinic_id', $clinicId)
            ->where('payment_status', 'paid')
            ->whereDate('payment_date', $date)
            ->when($isDoctorView, function ($query) use ($doctor) {
                $doctor
                    ? $query->whereHas('appointment', fn ($query) => $query->where('doctor_id', $doctor->id))
                    : $query->whereRaw('1 = 0');
            })
            ->sum('amount');
    }

    private function cancelPendingPayment(Appointment $appointment): void
    {
        $appointment->loadMissing('payment');

        if ($appointment->payment && $appointment->payment->payment_status === 'pending') {
            $appointment->payment->update([
                'payment_status' => 'cancelled',
                'notes' => 'Pago cancelado automaticamente porque la cita fue cancelada desde agenda.',
            ]);
        }
    }

    private function authorizeStatusChange(Appointment $appointment): void
    {
        abort_if((int) $appointment->clinic_id !== $this->clinicId(), 403);
        abort_unless(auth()->user()?->can('appointments.update'), 403);

        if ($this->isDoctorUser()) {
            $doctor = $this->authenticatedDoctor();
            abort_if(! $doctor || (int) $appointment->doctor_id !== (int) $doctor->id, 403);
        }
    }

    private function audit(string $action, Appointment $appointment, Request $request): void
    {
        $appointment->loadMissing('patient');

        AuditLog::create([
            'user_id' => $request->user()?->id,
            'action' => $action,
            'module' => 'appointments',
            'description' => "Cita #{$appointment->id} - {$appointment->patient?->full_name}",
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
        ]);
    }

    private function clinicId(): int
    {
        $clinicId = auth()->user()?->clinic_id;
        abort_if(! $clinicId, 403, 'El usuario autenticado no tiene una clinica asignada.');

        return (int) $clinicId;
    }

    private function doctors(int $clinicId)
    {
        return Doctor::with(['user', 'specialty'])
            ->where('clinic_id', $clinicId)
            ->where('status', 'active')
            ->get()
            ->sortBy(fn (Doctor $doctor) => $doctor->user?->name ?? '');
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

    private function isDoctorUser(): bool
    {
        return (bool) auth()->user()?->hasRole('medico');
    }

    private function appointmentStatuses(): array
    {
        return ['scheduled', 'confirmed', 'completed', 'cancelled', 'no_show'];
    }

    private function paymentStatuses(): array
    {
        return ['pending', 'paid', 'cancelled', 'refunded', 'without_payment'];
    }
}
