<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePaymentRequest;
use App\Http\Requests\UpdatePaymentRequest;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Service;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function index(Request $request): View
    {
        $clinicId = $this->clinicId();
        $search = trim((string) $request->query('search'));
        $paymentStatus = $request->query('payment_status');
        $paymentMethod = $request->query('payment_method');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');

        $baseQuery = Payment::where('clinic_id', $clinicId);


        $showPendingQueue = ($search === '' && blank($paymentStatus) && blank($paymentMethod) && blank($dateFrom) && blank($dateTo))
            || $paymentStatus === 'pending';
        $pendingPaymentQueue = $showPendingQueue
            ? Payment::with(['patient', 'appointment.doctor.user', 'service'])
                ->where('clinic_id', $clinicId)
                ->where('payment_status', 'pending')
                ->orderByRaw('CASE WHEN appointment_id IS NULL THEN 1 ELSE 0 END')
                ->orderByDesc('created_at')
                ->limit(8)
                ->get()
            : collect();
        $payments = Payment::query()
            ->with(['patient', 'appointment.doctor.user', 'service'])
            ->where('clinic_id', $clinicId)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('notes', 'like', "%{$search}%")
                        ->orWhereHas('patient', function ($query) use ($search) {
                            $query->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('service', fn ($query) => $query->where('name', 'like', "%{$search}%"));
                });
            })
            ->when(in_array($paymentStatus, ['pending', 'paid', 'cancelled', 'refunded'], true), fn ($query) => $query->where('payment_status', $paymentStatus))
            ->when(in_array($paymentMethod, ['cash', 'card', 'transfer', 'other'], true), fn ($query) => $query->where('payment_method', $paymentMethod))
            ->when($dateFrom, fn ($query) => $query->whereDate('payment_date', '>=', $dateFrom))
            ->when($dateTo, fn ($query) => $query->whereDate('payment_date', '<=', $dateTo))
            ->orderByRaw('payment_date IS NULL')
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        return view('payments.index', [
            'payments' => $payments,
            'pendingPaymentQueue' => $pendingPaymentQueue,
            'patients' => $this->patients($clinicId),
            'services' => $this->services($clinicId, onlyActive: false),
            'monthlyPaidIncome' => (clone $baseQuery)
                ->where('payment_status', 'paid')
                ->whereMonth('payment_date', now()->month)
                ->whereYear('payment_date', now()->year)
                ->sum('amount'),
            'todayPaidIncome' => (clone $baseQuery)
                ->where('payment_status', 'paid')
                ->whereDate('payment_date', today())
                ->sum('amount'),
            'todayPaidPaymentsCount' => (clone $baseQuery)
                ->where('payment_status', 'paid')
                ->whereDate('payment_date', today())
                ->count(),
            'todayPendingPaymentsCount' => (clone $baseQuery)
                ->where('payment_status', 'pending')
                ->whereDate('created_at', today())
                ->count(),
            'pendingPaymentsAmount' => (clone $baseQuery)->where('payment_status', 'pending')->sum('amount'),
            'pendingPaymentsCount' => (clone $baseQuery)->where('payment_status', 'pending')->count(),
            'totalPaidIncome' => (clone $baseQuery)->where('payment_status', 'paid')->sum('amount'),
            'cancelledOrRefundedCount' => (clone $baseQuery)->whereIn('payment_status', ['cancelled', 'refunded'])->count(),
            'search' => $search,
            'paymentStatus' => $paymentStatus,
            'paymentMethod' => $paymentMethod,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
        ]);
    }

    public function create(): View
    {
        return view('payments.create', $this->formData($this->clinicId()));
    }

    public function store(StorePaymentRequest $request): RedirectResponse
    {
        $data = $this->prepareData($request->validated(), $this->clinicId());
        $payment = Payment::create($data);
        AuditLogger::log($this->paymentAction($payment, null), 'payments', $payment, [], AuditLogger::modelSnapshot($payment), 'Pago creado.');
        $this->syncAppointmentAfterPayment($payment);

        return redirect()
            ->route('payments.index')
            ->with('success', 'Pago creado correctamente.');
    }

    public function show(Payment $payment): View
    {
        $this->authorizeClinic($payment);

        return view('payments.show', [
            'payment' => $payment->load(['patient', 'appointment.doctor.user', 'service']),
        ]);
    }

    public function edit(Payment $payment): View
    {
        $this->authorizeClinic($payment);

        return view('payments.edit', [
            'payment' => $payment->load(['patient', 'appointment', 'service']),
            ...$this->formData($this->clinicId()),
        ]);
    }

    public function update(UpdatePaymentRequest $request, Payment $payment): RedirectResponse
    {
        $this->authorizeClinic($payment);
        $old = AuditLogger::modelSnapshot($payment);
        $oldStatus = $payment->payment_status;
        $payment->update($this->prepareData($request->validated(), $this->clinicId()));
        $payment->refresh();
        AuditLogger::log($this->paymentAction($payment, $oldStatus), 'payments', $payment, $old, AuditLogger::modelSnapshot($payment), 'Pago actualizado.');
        $this->syncAppointmentAfterPayment($payment);

        return redirect()
            ->route('payments.show', $payment)
            ->with('success', 'Pago actualizado correctamente.');
    }

    public function destroy(Payment $payment): RedirectResponse
    {
        $this->authorizeClinic($payment);
        $old = AuditLogger::modelSnapshot($payment);
        AuditLogger::log('payment.deleted', 'payments', $payment, $old, [], 'Pago eliminado.');

        $payment->delete();

        return redirect()
            ->route('payments.index')
            ->with('success', 'Pago eliminado correctamente.');
    }

    private function prepareData(array $validated, int $clinicId): array
    {
        $patient = Patient::where('clinic_id', $clinicId)->find($validated['patient_id']);
        $appointment = isset($validated['appointment_id']) && $validated['appointment_id']
            ? Appointment::where('clinic_id', $clinicId)->find($validated['appointment_id'])
            : null;
        $service = isset($validated['service_id']) && $validated['service_id']
            ? Service::where('clinic_id', $clinicId)->find($validated['service_id'])
            : null;

        if (! $patient || (($validated['appointment_id'] ?? null) && ! $appointment) || (($validated['service_id'] ?? null) && ! $service)) {
            throw ValidationException::withMessages([
                'clinic_id' => 'Los datos seleccionados no pertenecen a la clÃ­nica del usuario autenticado.',
            ]);
        }

        if ($appointment && (int) $appointment->patient_id !== (int) $patient->id) {
            throw ValidationException::withMessages([
                'appointment_id' => 'La cita seleccionada no coincide con el paciente indicado.',
            ]);
        }

        $paymentDate = $validated['payment_date'] ?? null;
        if ($validated['payment_status'] === 'paid' && blank($paymentDate)) {
            $paymentDate = now();
        }

        return [
            'clinic_id' => $clinicId,
            'patient_id' => $patient->id,
            'appointment_id' => $appointment?->id,
            'service_id' => $service?->id,
            'amount' => $validated['amount'],
            'payment_method' => $validated['payment_method'],
            'payment_status' => $validated['payment_status'],
            'payment_date' => $paymentDate,
            'notes' => $validated['notes'] ?? null,
        ];
    }

    private function clinicId(): int
    {
        $clinicId = auth()->user()?->clinic_id;
        abort_if(! $clinicId, 403, 'El usuario autenticado no tiene una clÃ­nica asignada.');

        return (int) $clinicId;
    }

    private function authorizeClinic(Payment $payment): void
    {
        abort_if((int) $payment->clinic_id !== $this->clinicId(), 403);
    }

    private function formData(int $clinicId): array
    {
        return [
            'patients' => $this->patients($clinicId),
            'appointments' => Appointment::with(['patient', 'doctor.user'])
                ->where('clinic_id', $clinicId)
                ->orderByDesc('appointment_date')
                ->orderBy('start_time')
                ->get(),
            'services' => $this->services($clinicId),
        ];
    }

    private function patients(int $clinicId)
    {
        return Patient::where('clinic_id', $clinicId)
            ->where('status', 'active')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    private function services(int $clinicId, bool $onlyActive = true)
    {
        return Service::where('clinic_id', $clinicId)
            ->when($onlyActive, fn ($query) => $query->where('status', 'active'))
            ->orderBy('name')
            ->get();
    }

    private function paymentAction(Payment $payment, ?string $oldStatus): string
    {
        if ($payment->payment_status === 'paid' && $oldStatus !== 'paid') {
            return 'payment.paid';
        }

        if ($payment->payment_status === 'cancelled' && $oldStatus !== 'cancelled') {
            return 'payment.cancelled';
        }

        if ($payment->payment_status === 'refunded' && $oldStatus !== 'refunded') {
            return 'payment.refunded';
        }

        if ($payment->payment_status === 'pending' && $oldStatus === null) {
            return 'payment.pending_created';
        }

        return 'payment.updated';
    }

    private function syncAppointmentAfterPayment(Payment $payment): void
    {
        $appointment = $payment->appointment;

        if (! $appointment || $payment->payment_status !== 'paid') {
            return;
        }

        if ($appointment->status === 'scheduled') {
            $old = AuditLogger::modelSnapshot($appointment);
            $appointment->update(['status' => 'confirmed']);
            AuditLogger::log('appointment.confirmed', 'appointments', $appointment, $old, AuditLogger::modelSnapshot($appointment), 'Cita confirmada automÃ¡ticamente al registrar el pago.');
        }
    }
}


