<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesClinic;

use App\Http\Requests\StorePaymentRequest;
use App\Http\Requests\UpdatePaymentRequest;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Service;
use App\Services\AuditLogger;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class PaymentController extends Controller
{
    use ResolvesClinic;

    public function index(Request $request): View
    {
        $clinicId = $this->clinicId();
        $search = trim((string) $request->query('search'));
        $paymentStatus = $request->query('payment_status');
        $paymentMethod = $request->query('payment_method');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $localNow = $this->localNow();
        $localToday = $localNow->toDateString();

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
                ->whereMonth('payment_date', $localNow->month)
                ->whereYear('payment_date', $localNow->year)
                ->sum('amount'),
            'todayPaidIncome' => (clone $baseQuery)
                ->where('payment_status', 'paid')
                ->whereDate('payment_date', $localToday)
                ->sum('amount'),
            'todayPaidPaymentsCount' => (clone $baseQuery)
                ->where('payment_status', 'paid')
                ->whereDate('payment_date', $localToday)
                ->count(),
            'todayPendingPaymentsCount' => (clone $baseQuery)
                ->where('payment_status', 'pending')
                ->whereDate('created_at', $localToday)
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
            'payment' => $this->loadForReceipt($payment),
            'receiptNumber' => $this->receiptNumber($payment),
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
        $payment->update($this->prepareData($request->validated(), $this->clinicId(), $payment));
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

    public function receipt(Payment $payment): Response
    {
        $this->authorizeReceipt($payment);
        $payment = $this->loadForReceipt($payment);

        AuditLogger::log(
            'payment.receipt_downloaded',
            'payments',
            $payment,
            [],
            $this->receiptAuditContext($payment),
            'Recibo PDF de pago descargado.'
        );

        return Pdf::loadView('payments.receipt-print', [
            'payment' => $payment,
            'clinic' => $payment->clinic,
            'receiptNumber' => $this->receiptNumber($payment),
            'generatedAt' => $this->localNow(),
            'generatedBy' => auth()->user(),
            'forPdf' => true,
        ])->setPaper('a4')->download($this->receiptFileName($payment));
    }

    public function receiptPrint(Payment $payment): View
    {
        $this->authorizeReceipt($payment);
        $payment = $this->loadForReceipt($payment);

        AuditLogger::log(
            'payment.receipt_printed',
            'payments',
            $payment,
            [],
            $this->receiptAuditContext($payment),
            'Recibo de pago abierto para impresion.'
        );

        return view('payments.receipt-print', [
            'payment' => $payment,
            'clinic' => $payment->clinic,
            'receiptNumber' => $this->receiptNumber($payment),
            'generatedAt' => $this->localNow(),
            'generatedBy' => auth()->user(),
            'forPdf' => false,
        ]);
    }

    private function prepareData(array $validated, int $clinicId, ?Payment $payment = null): array
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
                'clinic_id' => 'Los datos seleccionados no pertenecen a la clinica del usuario autenticado.',
            ]);
        }

        if ($appointment && (int) $appointment->patient_id !== (int) $patient->id) {
            throw ValidationException::withMessages([
                'appointment_id' => 'La cita seleccionada no coincide con el paciente indicado.',
            ]);
        }

        $paymentDate = $validated['payment_date'] ?? null;
        if ($validated['payment_status'] === 'paid' && blank($paymentDate)) {
            $paymentDate = $payment?->payment_status === 'paid' && $payment->payment_date
                ? $payment->payment_date
                : $this->localNow();
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

    private function localNow(): Carbon
    {
        return now(config('app.timezone', 'America/Guayaquil'));
    }


    private function authorizeClinic(Payment $payment): void
    {
        abort_if((int) $payment->clinic_id !== $this->clinicId(), 403);
    }

    private function authorizeReceipt(Payment $payment): void
    {
        abort_unless(auth()->user()?->can('payments.view'), 403);
        $this->authorizeClinic($payment);
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
            AuditLogger::log('appointment.confirmed', 'appointments', $appointment, $old, AuditLogger::modelSnapshot($appointment), 'Cita confirmada automaticamente al registrar el pago.');
        }
    }

    private function loadForReceipt(Payment $payment): Payment
    {
        return $payment->load([
            'clinic',
            'patient.clinic',
            'appointment.doctor.user',
            'appointment.service',
            'service',
        ]);
    }

    private function receiptNumber(Payment $payment): string
    {
        return 'REC-'.str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT);
    }

    private function receiptFileName(Payment $payment): string
    {
        return 'recibo-pago-'.$this->receiptNumber($payment).'.pdf';
    }

    private function receiptAuditContext(Payment $payment): array
    {
        return [
            'payment_id' => $payment->id,
            'patient_id' => $payment->patient_id,
            'appointment_id' => $payment->appointment_id,
            'amount' => $payment->amount,
            'status' => $payment->payment_status,
            'method' => $payment->payment_method,
            'receipt_number' => $this->receiptNumber($payment),
        ];
    }
}
