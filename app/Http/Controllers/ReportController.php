<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportFilterRequest;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Consultation;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Prescription;
use App\Models\Service;
use App\Models\Specialty;
use App\Services\AuditLogger;
use App\Services\FinancialXlsxExporter;
use App\Services\AppointmentsXlsxExporter;
use App\Services\ClinicalXlsxExporter;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(ReportFilterRequest $request): View|RedirectResponse
    {
        [$clinicId, $filters, $start, $end] = $this->context($request);
        $user = $request->user();

        if (! $this->canViewGeneralReport($user)) {
            $firstReportRoute = $this->firstAllowedReportRoute($user);
            abort_if(! $firstReportRoute, 403);

            return redirect()->route($firstReportRoute, $request->query());
        }
        $appointments = $user->can('reports.appointments')
            ? $this->appointmentsQuery($clinicId, $start, $end)
            : Appointment::query()->whereRaw('1 = 0');
        $consultations = $user->can('reports.clinical')
            ? $this->consultationsQuery($clinicId, $start, $end)
            : Consultation::query()->whereRaw('1 = 0');
        $prescriptions = $user->can('reports.clinical')
            ? $this->prescriptionsQuery($clinicId, $start, $end)
            : Prescription::query()->whereRaw('1 = 0');
        $payments = $user->can('reports.financial')
            ? $this->paymentPeriod(Payment::where('clinic_id', $clinicId), $start, $end)
            : Payment::query()->whereRaw('1 = 0');

        $appointmentCounts = (clone $appointments)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('reports.index', [
            ...$this->shared($clinicId, $filters, $start, $end),
            'metrics' => [
                'activePatients' => $user->can('patients.view') ? Patient::where('clinic_id', $clinicId)->where('status', 'active')->count() : 0,
                'newPatients' => $user->can('reports.patients') ? Patient::where('clinic_id', $clinicId)->whereBetween('created_at', [$start, $end])->count() : 0,
                'activeDoctors' => $user->can('doctors.view') ? Doctor::where('clinic_id', $clinicId)->where('status', 'active')->count() : 0,
                'activeServices' => $user->can('services.view') ? Service::where('clinic_id', $clinicId)->where('status', 'active')->count() : 0,
                'appointments' => (clone $appointments)->count(),
                'todayAppointments' => $user->can('reports.appointments')
                    ? Appointment::where('clinic_id', $clinicId)->whereDate('appointment_date', $this->localNow()->toDateString())->count()
                    : 0,
                'completedAppointments' => (int) ($appointmentCounts['completed'] ?? 0),
                'cancelledAppointments' => (int) ($appointmentCounts['cancelled'] ?? 0),
                'noShowAppointments' => (int) ($appointmentCounts['no_show'] ?? 0),
                'consultations' => (clone $consultations)->count(),
                'prescriptions' => (clone $prescriptions)->count(),
                'paidIncome' => (float) (clone $payments)->where('payment_status', 'paid')->sum('amount'),
                'pendingPayments' => (clone $payments)->where('payment_status', 'pending')->count(),
                'cancelledOrRefundedPayments' => (clone $payments)->whereIn('payment_status', ['cancelled', 'refunded'])->count(),
            ],
            'latestAppointments' => (clone $appointments)->with(['patient', 'doctor.user', 'service'])->latest('appointment_date')->latest('start_time')->limit(5)->get(),
            'latestPayments' => (clone $payments)->with(['patient', 'service'])->latest('created_at')->limit(5)->get(),
            'topServices' => (clone $appointments)->whereNotNull('service_id')->with('service:id,name')->selectRaw('service_id, COUNT(*) as total')->groupBy('service_id')->orderByDesc('total')->limit(5)->get(),
            'topDoctors' => (clone $consultations)->with('doctor.user:id,name')->selectRaw('doctor_id, COUNT(*) as total')->groupBy('doctor_id')->orderByDesc('total')->limit(5)->get(),
            'appointmentStatusCounts' => $appointmentCounts,
        ]);
    }

    public function appointments(ReportFilterRequest $request): View
    {
        [$clinicId, $filters, $start, $end] = $this->context($request);
        $report = $this->appointmentsReportData($clinicId, $filters, $start, $end);

        return view('reports.appointments', [
            ...$this->shared($clinicId, $filters, $start, $end),
            ...$report,
            'appointments' => (clone $report['query'])->paginate(15)->withQueryString(),
        ]);
    }

    public function clinical(ReportFilterRequest $request): View
    {
        [$clinicId, $filters, $start, $end] = $this->context($request);
        $report = $this->clinicalReportData($clinicId, $filters, $start, $end);

        return view('reports.clinical', [
            ...$this->shared($clinicId, $filters, $start, $end),
            ...$report,
        ]);
    }

    public function financial(ReportFilterRequest $request): View
    {
        [$clinicId, $filters, $start, $end] = $this->context($request);
        $report = $this->financialReportData($clinicId, $filters, $start, $end);

        return view('reports.financial', [
            ...$this->shared($clinicId, $filters, $start, $end),
            ...$report,
            'payments' => (clone $report['query'])->paginate(15)->withQueryString(),
        ]);
    }

    public function financialPdf(ReportFilterRequest $request): Response
    {
        [$clinicId, $filters, $start, $end] = $this->context($request);
        $report = $this->financialReportData($clinicId, $filters, $start, $end);
        $payments = (clone $report['query'])->get();

        $this->auditFinancialExport('report.financial_exported_pdf', 'pdf', $clinicId, $filters, $report['metrics']);

        return Pdf::loadView('reports.financial-print', [
            ...$this->shared($clinicId, $filters, $start, $end),
            ...$report,
            'payments' => $payments,
            'clinic' => Clinic::find($clinicId),
            'generatedAt' => $this->localNow(),
            'generatedBy' => $request->user(),
            'forPdf' => true,
        ])->setPaper('a4', 'landscape')->download('reporte-financiero-'.$this->localNow()->format('Y-m-d').'.pdf');
    }

    public function financialCsv(ReportFilterRequest $request): StreamedResponse
    {
        [$clinicId, $filters, $start, $end] = $this->context($request);
        $report = $this->financialReportData($clinicId, $filters, $start, $end);
        $payments = (clone $report['query'])->get();
        $timezone = config('app.timezone', 'America/Guayaquil');

        $this->auditFinancialExport('report.financial_exported_csv', 'csv', $clinicId, $filters, $report['metrics']);

        return response()->streamDownload(function () use ($payments, $timezone): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($handle, [
                'Número de pago',
                'Fecha de pago',
                'Paciente',
                'Identificación',
                'Servicio',
                'Médico',
                'Método de pago',
                'Estado',
                'Monto',
            ], ';');

            foreach ($payments as $payment) {
                fputcsv($handle, [
                    $this->paymentNumber($payment),
                    $payment->payment_date?->timezone($timezone)->format('d/m/Y H:i') ?? 'Sin fecha',
                    $payment->patient?->full_name ?? 'Sin paciente',
                    $payment->patient?->identification_number ?? 'Sin registrar',
                    $payment->service?->name ?? 'Sin servicio',
                    $payment->appointment?->doctor?->user?->name ?? 'Sin médico',
                    $this->paymentMethodLabels()[$payment->payment_method] ?? $payment->payment_method,
                    $this->paymentStatusLabels()[$payment->payment_status] ?? $payment->payment_status,
                    number_format((float) $payment->amount, 2, '.', ''),
                ], ';');
            }

            fclose($handle);
        }, 'reporte-financiero-'.$this->localNow()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
    public function financialXlsx(ReportFilterRequest $request, FinancialXlsxExporter $exporter): StreamedResponse
    {
        [$clinicId, $filters, $start, $end] = $this->context($request);
        $report = $this->financialReportData($clinicId, $filters, $start, $end);
        $shared = $this->shared($clinicId, $filters, $start, $end);
        $clinic = Clinic::findOrFail($clinicId);
        $payments = (clone $report['query'])->get();
        $timezone = config('app.timezone', 'America/Guayaquil');
        $generatedAt = $this->localNow();

        $this->auditFinancialExport('report.financial_exported_xlsx', 'xlsx', $clinicId, $filters, $report['metrics']);

        return response()->streamDownload(function () use ($exporter, $payments, $report, $shared, $clinic, $timezone, $generatedAt, $request): void {
            $exporter->stream(
                payments: $payments,
                metrics: $report['metrics'],
                methodLabels: $report['methodLabels'],
                statusLabels: $report['statusLabels'],
                clinic: $clinic,
                periodLabel: $shared['periodLabel'],
                generatedAt: $generatedAt,
                generatedBy: $request->user(),
                timezone: $timezone,
            );
        }, $this->financialXlsxFileName($clinic, $generatedAt), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
    public function financialPrint(ReportFilterRequest $request): View
    {
        [$clinicId, $filters, $start, $end] = $this->context($request);
        $report = $this->financialReportData($clinicId, $filters, $start, $end);

        $this->auditFinancialExport('report.financial_printed', 'print', $clinicId, $filters, $report['metrics']);

        return view('reports.financial-print', [
            ...$this->shared($clinicId, $filters, $start, $end),
            ...$report,
            'payments' => (clone $report['query'])->get(),
            'clinic' => Clinic::find($clinicId),
            'generatedAt' => $this->localNow(),
            'generatedBy' => $request->user(),
            'forPdf' => false,
        ]);
    }

    public function patients(ReportFilterRequest $request): View
    {
        [$clinicId, $filters, $start, $end] = $this->context($request);
        $base = Patient::where('clinic_id', $clinicId);
        $query = Patient::where('clinic_id', $clinicId)
            ->whereBetween('created_at', [$start, $end])
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->with('medicalRecord')
            ->withCount([
                'appointments' => fn ($query) => $query->whereBetween('appointment_date', [$start->toDateString(), $end->toDateString()]),
                'consultations' => fn ($query) => $query->whereBetween('consultation_date', [$start, $end]),
            ]);

        return view('reports.patients', [
            ...$this->shared($clinicId, $filters, $start, $end),
            'patients' => (clone $query)->latest()->paginate(15)->withQueryString(),
            'metrics' => [
                'total' => (clone $base)->count(),
                'active' => (clone $base)->where('status', 'active')->count(),
                'inactive' => (clone $base)->where('status', 'inactive')->count(),
                'new' => (clone $base)->whereBetween('created_at', [$start, $end])->count(),
                'withMedicalRecord' => (clone $base)->has('medicalRecord')->count(),
                'withoutMedicalRecord' => (clone $base)->doesntHave('medicalRecord')->count(),
                'withAllergies' => (clone $base)->whereNotNull('allergies')->where('allergies', '<>', '')->count(),
                'withEmergencyContact' => (clone $base)->whereNotNull('emergency_contact_name')->where('emergency_contact_name', '<>', '')->count(),
            ],
            'topByAppointments' => Patient::where('clinic_id', $clinicId)->withCount(['appointments' => fn ($query) => $query->whereBetween('appointment_date', [$start->toDateString(), $end->toDateString()])])->orderByDesc('appointments_count')->limit(10)->get(),
            'topByConsultations' => Patient::where('clinic_id', $clinicId)->withCount(['consultations' => fn ($query) => $query->whereBetween('consultation_date', [$start, $end])])->orderByDesc('consultations_count')->limit(10)->get(),
        ]);
    }

    public function doctors(ReportFilterRequest $request): View
    {
        [$clinicId, $filters, $start, $end] = $this->context($request);
        $base = Doctor::where('clinic_id', $clinicId);
        $query = Doctor::where('clinic_id', $clinicId)
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['specialty_id'] ?? null, fn ($query, $id) => $query->where('specialty_id', $id))
            ->with(['user', 'specialty'])
            ->withCount([
                'appointments' => fn ($query) => $query->whereBetween('appointment_date', [$start->toDateString(), $end->toDateString()]),
                'consultations' => fn ($query) => $query->whereBetween('consultation_date', [$start, $end]),
                'prescriptions' => fn ($query) => $query->whereBetween('prescription_date', [$start->toDateString(), $end->toDateString()]),
            ]);
        $incomeByDoctor = $this->paymentPeriod(
            Payment::query()->join('appointments', 'payments.appointment_id', '=', 'appointments.id')
                ->where('payments.clinic_id', $clinicId)
                ->where('payments.payment_status', 'paid'),
            $start,
            $end,
            'payments.payment_date',
            'payments.created_at'
        )->selectRaw('appointments.doctor_id, SUM(payments.amount) as total')->groupBy('appointments.doctor_id')->pluck('total', 'appointments.doctor_id');
        $doctorRows = (clone $query)->orderByDesc('consultations_count')->paginate(15)->withQueryString();
        $doctorRows->getCollection()->each(fn ($doctor) => $doctor->setAttribute('associated_income', (float) ($incomeByDoctor[$doctor->id] ?? 0)));

        return view('reports.doctors', [
            ...$this->shared($clinicId, $filters, $start, $end),
            'doctorsReport' => $doctorRows,
            'metrics' => [
                'active' => (clone $base)->where('status', 'active')->count(),
                'inactive' => (clone $base)->where('status', 'inactive')->count(),
                'appointments' => $this->appointmentsQuery($clinicId, $start, $end)->count(),
                'consultations' => $this->consultationsQuery($clinicId, $start, $end)->count(),
                'prescriptions' => $this->prescriptionsQuery($clinicId, $start, $end)->count(),
                'averageConsultations' => round($this->consultationsQuery($clinicId, $start, $end)->count() / max((clone $base)->count(), 1), 1),
            ],
            'incomeByDoctor' => $incomeByDoctor,
        ]);
    }

    public function services(ReportFilterRequest $request): View
    {
        [$clinicId, $filters, $start, $end] = $this->context($request);
        $base = Service::where('clinic_id', $clinicId);
        $query = Service::where('clinic_id', $clinicId)
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['service_id'] ?? null, fn ($query, $id) => $query->whereKey($id))
            ->withCount([
                'appointments' => fn ($query) => $query->whereBetween('appointment_date', [$start->toDateString(), $end->toDateString()]),
                'payments' => fn ($query) => $this->paymentPeriod($query, $start, $end),
            ]);
        $incomeByService = $this->paymentPeriod(
            Payment::where('clinic_id', $clinicId)->where('payment_status', 'paid')->whereNotNull('service_id'),
            $start,
            $end
        )->selectRaw('service_id, SUM(amount) as total')->groupBy('service_id')->pluck('total', 'service_id');
        $serviceRows = (clone $query)->orderByDesc('appointments_count')->paginate(15)->withQueryString();
        $serviceRows->getCollection()->each(fn ($service) => $service->setAttribute('paid_income', (float) ($incomeByService[$service->id] ?? 0)));

        return view('reports.services', [
            ...$this->shared($clinicId, $filters, $start, $end),
            'servicesReport' => $serviceRows,
            'metrics' => [
                'active' => (clone $base)->where('status', 'active')->count(),
                'inactive' => (clone $base)->where('status', 'inactive')->count(),
                'averagePrice' => (float) ((clone $base)->avg('price') ?? 0),
                'averageDuration' => round((float) ((clone $base)->avg('duration_minutes') ?? 0), 1),
                'totalAppointments' => $this->appointmentsQuery($clinicId, $start, $end)->whereNotNull('service_id')->count(),
                'paidIncome' => (float) array_sum($incomeByService->all()),
            ],
            'incomeByService' => $incomeByService,
        ]);
    }

    /** @return array{0: int, 1: array<string, mixed>, 2: Carbon, 3: Carbon} */
    private function context(ReportFilterRequest $request): array
    {
        $clinicId = auth()->user()?->activeClinicId();
        abort_if(! $clinicId, 403, 'El usuario autenticado no tiene una clinica asignada.');
        $filters = $request->validated();
        $timezone = config('app.timezone', 'America/Guayaquil');
        $localNow = $this->localNow();
        $start = Carbon::parse($filters['start_date'] ?? $localNow->copy()->startOfMonth()->toDateString(), $timezone)->startOfDay();
        $end = Carbon::parse($filters['end_date'] ?? $localNow->copy()->endOfMonth()->toDateString(), $timezone)->endOfDay();

        return [(int) $clinicId, $filters, $start, $end];
    }

    /** @return array<string, mixed> */
    private function shared(int $clinicId, array $filters, Carbon $start, Carbon $end): array
    {
        $doctorQuery = Doctor::with('user')->where('clinic_id', $clinicId)->orderBy('id');
        if (auth()->user()?->hasRole('medico')) {
            $doctorId = $this->doctorIdForReportUser($clinicId);
            $doctorId ? $doctorQuery->whereKey($doctorId) : $doctorQuery->whereRaw('1 = 0');
        }

        return [
            'filters' => $filters,
            'startDate' => $start->toDateString(),
            'endDate' => $end->toDateString(),
            'periodLabel' => $start->format('d/m/Y').' - '.$end->format('d/m/Y'),
            'doctors' => $doctorQuery->get(),
            'services' => Service::where('clinic_id', $clinicId)->orderBy('name')->get(),
            'patientsList' => Patient::where('clinic_id', $clinicId)->orderBy('last_name')->orderBy('first_name')->get(),
            'specialties' => Specialty::whereHas('doctors', fn ($query) => $query->where('clinic_id', $clinicId))->orderBy('name')->get(),
        ];
    }

    /** @return array<string, mixed> */
    private function financialReportData(int $clinicId, array $filters, Carbon $start, Carbon $end): array
    {
        $query = $this->financialQuery($clinicId, $filters, $start, $end);
        $paid = (clone $query)->where('payment_status', 'paid');
        $pending = (clone $query)->where('payment_status', 'pending');
        $cancelled = (clone $query)->where('payment_status', 'cancelled');
        $refunded = (clone $query)->where('payment_status', 'refunded');
        $totalPayments = (clone $query)->count();

        return [
            'query' => $query,
            'metrics' => [
                'totalAmount' => (float) (clone $query)->sum('amount'),
                'paidIncome' => (float) (clone $paid)->sum('amount'),
                'pendingAmount' => (float) (clone $pending)->sum('amount'),
                'cancelledAmount' => (float) (clone $cancelled)->sum('amount'),
                'refundedAmount' => (float) (clone $refunded)->sum('amount'),
                'totalPayments' => $totalPayments,
                'paidPayments' => (clone $paid)->count(),
                'pending' => (clone $pending)->count(),
                'cancelled' => (clone $cancelled)->count(),
                'refunded' => (clone $refunded)->count(),
                'averagePayment' => (float) ((clone $paid)->avg('amount') ?? 0),
                'patientsWithPendingPayments' => (clone $pending)->distinct('patient_id')->count('patient_id'),
            ],
            'methodTotals' => (clone $query)
                ->reorder()
                ->selectRaw('payment_method, COUNT(*) as total_count, SUM(amount) as total_amount')
                ->groupBy('payment_method')
                ->get()
                ->keyBy('payment_method'),
            'incomeByService' => (clone $paid)
                ->reorder()
                ->whereNotNull('service_id')
                ->with('service:id,name')
                ->selectRaw('service_id, SUM(amount) as total')
                ->groupBy('service_id')
                ->orderByDesc('total')
                ->limit(10)
                ->get(),
            'methodLabels' => $this->paymentMethodLabels(),
            'statusLabels' => $this->paymentStatusLabels(),
        ];
    }

    /** @return array<string, mixed> */
    private function appointmentsReportData(int $clinicId, array $filters, Carbon $start, Carbon $end): array
    {
        $doctorFilter = auth()->user()?->hasRole('medico') ? null : ($filters['doctor_id'] ?? null);
        $query = $this->appointmentsQuery($clinicId, $start, $end)
            ->when($doctorFilter, fn ($query, $id) => $query->where('doctor_id', $id))
            ->when($filters['service_id'] ?? null, fn ($query, $id) => $query->where('service_id', $id))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status));
        $this->scopeDoctorReportQuery($query, $clinicId);
        $total = (clone $query)->count();
        $statusCounts = (clone $query)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        return [
            'query' => (clone $query)->with(['patient', 'doctor.user', 'service'])->orderByDesc('appointment_date')->orderBy('start_time'),
            'metrics' => [
                'total' => $total,
                'scheduled' => (int) ($statusCounts['scheduled'] ?? 0),
                'confirmed' => (int) ($statusCounts['confirmed'] ?? 0),
                'completed' => (int) ($statusCounts['completed'] ?? 0),
                'cancelled' => (int) ($statusCounts['cancelled'] ?? 0),
                'noShow' => (int) ($statusCounts['no_show'] ?? 0),
                'cancellationRate' => $total ? round(((int) ($statusCounts['cancelled'] ?? 0) / $total) * 100, 1) : 0,
                'noShowRate' => $total ? round(((int) ($statusCounts['no_show'] ?? 0) / $total) * 100, 1) : 0,
            ],
            'statusCounts' => $statusCounts,
            'appointmentsByDoctor' => (clone $query)->with('doctor.user:id,name')->selectRaw('doctor_id, COUNT(*) as total')->groupBy('doctor_id')->orderByDesc('total')->limit(10)->get(),
            'appointmentsByService' => (clone $query)->whereNotNull('service_id')->with('service:id,name')->selectRaw('service_id, COUNT(*) as total')->groupBy('service_id')->orderByDesc('total')->limit(10)->get(),
            'appointmentsByDay' => (clone $query)->selectRaw('appointment_date, COUNT(*) as total')->groupBy('appointment_date')->orderBy('appointment_date')->get(),
        ];
    }

    /** @return array<string, mixed> */
    private function clinicalReportData(int $clinicId, array $filters, Carbon $start, Carbon $end): array
    {
        $doctorFilter = auth()->user()?->hasRole('medico') ? null : ($filters['doctor_id'] ?? null);
        $consultations = $this->consultationsQuery($clinicId, $start, $end)
            ->when($doctorFilter, fn ($query, $id) => $query->where('doctor_id', $id))
            ->when($filters['patient_id'] ?? null, fn ($query, $id) => $query->where('patient_id', $id));
        $prescriptions = $this->prescriptionsQuery($clinicId, $start, $end)
            ->when($doctorFilter, fn ($query, $id) => $query->where('doctor_id', $id))
            ->when($filters['patient_id'] ?? null, fn ($query, $id) => $query->where('patient_id', $id));
        $this->scopeDoctorReportQuery($consultations, $clinicId);
        $this->scopeDoctorReportQuery($prescriptions, $clinicId);
        $patients = Patient::where('clinic_id', $clinicId);

        return [
            'consultationsQuery' => (clone $consultations)->with(['patient', 'doctor.user'])->latest('consultation_date'),
            'metrics' => [
                'consultations' => (clone $consultations)->count(),
                'prescriptions' => (clone $prescriptions)->count(),
                'withMedicalRecord' => (clone $patients)->has('medicalRecord')->count(),
                'withoutMedicalRecord' => (clone $patients)->doesntHave('medicalRecord')->count(),
                'withAllergies' => (clone $patients)->whereNotNull('allergies')->where('allergies', '<>', '')->count(),
                'withChronicDiseases' => (clone $patients)->whereHas('medicalRecord', fn ($query) => $query->whereNotNull('chronic_diseases')->where('chronic_diseases', '<>', ''))->count(),
            ],
            'latestConsultations' => (clone $consultations)->with(['patient', 'doctor.user'])->latest('consultation_date')->limit(10)->get(),
            'recentPrescriptions' => (clone $prescriptions)->with(['patient', 'doctor.user'])->latest('prescription_date')->limit(10)->get(),
            'consultationsByDoctor' => (clone $consultations)->with('doctor.user:id,name')->selectRaw('doctor_id, COUNT(*) as total')->groupBy('doctor_id')->orderByDesc('total')->limit(10)->get(),
            'consultationsByPatient' => (clone $consultations)->with('patient:id,first_name,last_name')->selectRaw('patient_id, COUNT(*) as total')->groupBy('patient_id')->orderByDesc('total')->limit(10)->get(),
            'topDiagnoses' => (clone $consultations)->whereNotNull('diagnosis')->where('diagnosis', '<>', '')->selectRaw('diagnosis, COUNT(*) as total')->groupBy('diagnosis')->orderByDesc('total')->limit(10)->get(),
            'topTreatments' => (clone $consultations)->whereNotNull('treatment')->where('treatment', '<>', '')->selectRaw('treatment, COUNT(*) as total')->groupBy('treatment')->orderByDesc('total')->limit(10)->get(),
        ];
    }

    private function financialQuery(int $clinicId, array $filters, Carbon $start, Carbon $end): Builder
    {
        return $this->paymentPeriod(Payment::where('clinic_id', $clinicId), $start, $end)
            ->with(['patient', 'service', 'appointment.doctor.user'])
            ->when($filters['payment_status'] ?? null, fn ($query, $status) => $query->where('payment_status', $status))
            ->when($filters['payment_method'] ?? null, fn ($query, $method) => $query->where('payment_method', $method))
            ->when($filters['service_id'] ?? null, fn ($query, $id) => $query->where('service_id', $id))
            ->when($filters['doctor_id'] ?? null, fn ($query, $id) => $query->whereHas('appointment', fn ($query) => $query->where('doctor_id', $id)))
            ->orderByRaw('payment_date IS NULL')
            ->orderByDesc('payment_date')
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    private function canViewGeneralReport($user): bool
    {
        return collect($this->sectionReportPermissions())->every(fn ($permission) => $user?->can($permission));
    }

    private function firstAllowedReportRoute($user): ?string
    {
        foreach ($this->sectionReportRoutes() as $route => $permission) {
            if ($user?->can($permission)) {
                return $route;
            }
        }

        return null;
    }

    /** @return array<string, string> */
    private function sectionReportRoutes(): array
    {
        return [
            'reports.appointments' => 'reports.appointments',
            'reports.clinical' => 'reports.clinical',
            'reports.financial' => 'reports.financial',
            'reports.patients' => 'reports.patients',
            'reports.doctors' => 'reports.doctors',
            'reports.services' => 'reports.services',
        ];
    }

    /** @return array<int, string> */
    private function sectionReportPermissions(): array
    {
        return array_values($this->sectionReportRoutes());
    }
    private function appointmentsQuery(int $clinicId, Carbon $start, Carbon $end): Builder
    {
        return Appointment::where('clinic_id', $clinicId)->whereBetween('appointment_date', [$start->toDateString(), $end->toDateString()]);
    }

    private function consultationsQuery(int $clinicId, Carbon $start, Carbon $end): Builder
    {
        return Consultation::whereHas('patient', fn ($query) => $query->where('clinic_id', $clinicId))->whereBetween('consultation_date', [$start, $end]);
    }

    private function prescriptionsQuery(int $clinicId, Carbon $start, Carbon $end): Builder
    {
        return Prescription::whereHas('patient', fn ($query) => $query->where('clinic_id', $clinicId))->whereBetween('prescription_date', [$start->toDateString(), $end->toDateString()]);
    }

    private function paymentPeriod(Builder $query, Carbon $start, Carbon $end, string $paymentDate = 'payment_date', string $createdAt = 'created_at'): Builder
    {
        return $query->where(function ($query) use ($start, $end, $paymentDate, $createdAt) {
            $query->whereBetween($paymentDate, [$start, $end])
                ->orWhere(function ($query) use ($start, $end, $paymentDate, $createdAt) {
                    $query->whereNull($paymentDate)->whereBetween($createdAt, [$start, $end]);
                });
        });
    }

    private function scopeDoctorReportQuery(Builder $query, int $clinicId): void
    {
        $user = auth()->user();

        if (! $user?->hasRole('medico')) {
            return;
        }

        $doctorId = $this->doctorIdForReportUser($clinicId);
        $doctorId ? $query->where('doctor_id', $doctorId) : $query->whereRaw('1 = 0');
    }

    private function doctorIdForReportUser(int $clinicId): ?int
    {
        $user = auth()->user();

        if (! $user?->hasRole('medico')) {
            return null;
        }

        return Doctor::where('clinic_id', $clinicId)
            ->where('user_id', $user->id)
            ->value('id');
    }

    /** @return array<string, string> */
    private function paymentMethodLabels(): array
    {
        return ['cash' => 'Efectivo', 'card' => 'Tarjeta', 'transfer' => 'Transferencia', 'other' => 'Otro'];
    }

    /** @return array<string, string> */
    private function paymentStatusLabels(): array
    {
        return ['pending' => 'Pendiente', 'paid' => 'Pagado', 'cancelled' => 'Cancelado', 'refunded' => 'Reembolsado'];
    }

    private function paymentNumber(Payment $payment): string
    {
        return 'PAG-'.str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT);
    }

    private function localNow(): Carbon
    {
        return now(config('app.timezone', 'America/Guayaquil'));
    }

    private function financialXlsxFileName(Clinic $clinic, Carbon $generatedAt): string
    {
        $clinicSlug = Str::slug($clinic->name);

        return 'reporte-financiero-'.($clinicSlug ?: 'clinica').'-'.$generatedAt->format('Y-m-d').'.xlsx';
    }

    /** @param array<string, mixed> $filters */
    private function auditFinancialExport(string $action, string $format, int $clinicId, array $filters, array $metrics): void
    {
        AuditLogger::log($action, 'reports', null, [], [
            'date_from' => $filters['start_date'] ?? null,
            'date_to' => $filters['end_date'] ?? null,
            'filters' => $filters,
            'user_id' => auth()->id(),
            'clinic_id' => $clinicId,
            'total_amount' => $metrics['paidIncome'] ?? 0,
            'total_records' => $metrics['totalPayments'] ?? 0,
            'format' => $format,
        ], 'Reporte financiero exportado.');
    }

    public function appointmentsPdf(ReportFilterRequest $request): Response
    {
        [$clinicId, $filters, $start, $end] = $this->context($request);
        $report = $this->appointmentsReportData($clinicId, $filters, $start, $end);
        $appointments = (clone $report['query'])->get();

        $this->auditAppointmentsExport('report.appointments_exported_pdf', 'pdf', $clinicId, $filters, $report['metrics']);

        return Pdf::loadView('reports.appointments-print', [
            ...$this->shared($clinicId, $filters, $start, $end),
            ...$report,
            'appointmentsList' => $appointments,
            'clinic' => Clinic::find($clinicId),
            'generatedAt' => $this->localNow(),
            'generatedBy' => $request->user(),
            'forPdf' => true,
        ])->setPaper('a4', 'landscape')->download('reporte-citas-'.$this->localNow()->format('Y-m-d').'.pdf');
    }

    public function appointmentsCsv(ReportFilterRequest $request): StreamedResponse
    {
        [$clinicId, $filters, $start, $end] = $this->context($request);
        $report = $this->appointmentsReportData($clinicId, $filters, $start, $end);
        $appointments = (clone $report['query'])->get();
        $timezone = config('app.timezone', 'America/Guayaquil');

        $this->auditAppointmentsExport('report.appointments_exported_csv', 'csv', $clinicId, $filters, $report['metrics']);
        $statusOptions = ['scheduled' => 'Programada', 'confirmed' => 'Confirmada', 'completed' => 'Completada', 'cancelled' => 'Cancelada', 'no_show' => 'No asistió'];

        return response()->streamDownload(function () use ($appointments, $timezone, $statusOptions): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($handle, ['Nro. cita', 'Fecha', 'Hora', 'Paciente', 'Médico', 'Servicio', 'Estado', 'Motivo'], ';');

            foreach ($appointments as $appointment) {
                fputcsv($handle, [
                    'CIT-'.str_pad((string) $appointment->id, 6, '0', STR_PAD_LEFT),
                    $appointment->appointment_date?->format('d/m/Y') ?? 'Sin fecha',
                    substr((string)$appointment->start_time, 0, 5) ?? 'Sin hora',
                    $appointment->patient?->full_name ?? 'Sin paciente',
                    $appointment->doctor?->user?->name ?? 'Sin médico',
                    $appointment->service?->name ?? 'Sin servicio',
                    $statusOptions[$appointment->status] ?? $appointment->status,
                    $appointment->reason ?: 'Sin motivo',
                ], ';');
            }
            fclose($handle);
        }, 'reporte-citas-'.$this->localNow()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function appointmentsXlsx(ReportFilterRequest $request, AppointmentsXlsxExporter $exporter): StreamedResponse
    {
        [$clinicId, $filters, $start, $end] = $this->context($request);
        $report = $this->appointmentsReportData($clinicId, $filters, $start, $end);
        $shared = $this->shared($clinicId, $filters, $start, $end);
        $appointments = (clone $report['query'])->get();
        $timezone = config('app.timezone', 'America/Guayaquil');
        $generatedAt = $this->localNow();

        $this->auditAppointmentsExport('report.appointments_exported_xlsx', 'xlsx', $clinicId, $filters, $report['metrics']);
        $statusOptions = ['scheduled' => 'Programada', 'confirmed' => 'Confirmada', 'completed' => 'Completada', 'cancelled' => 'Cancelada', 'no_show' => 'No asistió'];

        return response()->streamDownload(function () use ($exporter, $appointments, $report, $shared, $timezone, $generatedAt, $request, $statusOptions): void {
            $exporter->stream(
                appointments: $appointments,
                metrics: $report['metrics'],
                statusOptions: $statusOptions,
                periodLabel: $shared['periodLabel'],
                generatedAt: $generatedAt,
                generatedBy: $request->user(),
                timezone: $timezone,
            );
        }, 'reporte-citas-'.$generatedAt->format('Y-m-d').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function appointmentsPrint(ReportFilterRequest $request): View
    {
        [$clinicId, $filters, $start, $end] = $this->context($request);
        $report = $this->appointmentsReportData($clinicId, $filters, $start, $end);

        $this->auditAppointmentsExport('report.appointments_printed', 'print', $clinicId, $filters, $report['metrics']);

        return view('reports.appointments-print', [
            ...$this->shared($clinicId, $filters, $start, $end),
            ...$report,
            'appointmentsList' => (clone $report['query'])->get(),
            'clinic' => Clinic::find($clinicId),
            'generatedAt' => $this->localNow(),
            'generatedBy' => $request->user(),
            'forPdf' => false,
        ]);
    }

    public function clinicalPdf(ReportFilterRequest $request): Response
    {
        [$clinicId, $filters, $start, $end] = $this->context($request);
        $report = $this->clinicalReportData($clinicId, $filters, $start, $end);
        $consultations = (clone $report['consultationsQuery'])->get();

        $this->auditClinicalExport('report.clinical_exported_pdf', 'pdf', $clinicId, $filters, $report['metrics']);

        return Pdf::loadView('reports.clinical-print', [
            ...$this->shared($clinicId, $filters, $start, $end),
            ...$report,
            'consultationsList' => $consultations,
            'clinic' => Clinic::find($clinicId),
            'generatedAt' => $this->localNow(),
            'generatedBy' => $request->user(),
            'forPdf' => true,
        ])->setPaper('a4', 'landscape')->download('reporte-clinico-'.$this->localNow()->format('Y-m-d').'.pdf');
    }

    public function clinicalCsv(ReportFilterRequest $request): StreamedResponse
    {
        [$clinicId, $filters, $start, $end] = $this->context($request);
        $report = $this->clinicalReportData($clinicId, $filters, $start, $end);
        $consultations = (clone $report['consultationsQuery'])->get();
        $timezone = config('app.timezone', 'America/Guayaquil');

        $this->auditClinicalExport('report.clinical_exported_csv', 'csv', $clinicId, $filters, $report['metrics']);

        return response()->streamDownload(function () use ($consultations, $timezone): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($handle, ['Nro. consulta', 'Fecha', 'Paciente', 'Médico', 'Diagnóstico', 'Tratamiento'], ';');

            foreach ($consultations as $consultation) {
                fputcsv($handle, [
                    'CON-'.str_pad((string) $consultation->id, 6, '0', STR_PAD_LEFT),
                    $consultation->consultation_date?->format('d/m/Y H:i') ?? 'Sin fecha',
                    $consultation->patient?->full_name ?? 'Sin paciente',
                    $consultation->doctor?->user?->name ?? 'Sin médico',
                    $consultation->diagnosis ?: 'Sin diagnóstico',
                    $consultation->treatment ?: 'Sin tratamiento',
                ], ';');
            }
            fclose($handle);
        }, 'reporte-clinico-'.$this->localNow()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function clinicalXlsx(ReportFilterRequest $request, ClinicalXlsxExporter $exporter): StreamedResponse
    {
        [$clinicId, $filters, $start, $end] = $this->context($request);
        $report = $this->clinicalReportData($clinicId, $filters, $start, $end);
        $shared = $this->shared($clinicId, $filters, $start, $end);
        $consultations = (clone $report['consultationsQuery'])->get();
        $timezone = config('app.timezone', 'America/Guayaquil');
        $generatedAt = $this->localNow();

        $this->auditClinicalExport('report.clinical_exported_xlsx', 'xlsx', $clinicId, $filters, $report['metrics']);

        return response()->streamDownload(function () use ($exporter, $consultations, $report, $shared, $timezone, $generatedAt, $request): void {
            $exporter->stream(
                consultations: $consultations,
                metrics: $report['metrics'],
                periodLabel: $shared['periodLabel'],
                generatedAt: $generatedAt,
                generatedBy: $request->user(),
                timezone: $timezone,
            );
        }, 'reporte-clinico-'.$generatedAt->format('Y-m-d').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function clinicalPrint(ReportFilterRequest $request): View
    {
        [$clinicId, $filters, $start, $end] = $this->context($request);
        $report = $this->clinicalReportData($clinicId, $filters, $start, $end);

        $this->auditClinicalExport('report.clinical_printed', 'print', $clinicId, $filters, $report['metrics']);

        return view('reports.clinical-print', [
            ...$this->shared($clinicId, $filters, $start, $end),
            ...$report,
            'consultationsList' => (clone $report['consultationsQuery'])->get(),
            'clinic' => Clinic::find($clinicId),
            'generatedAt' => $this->localNow(),
            'generatedBy' => $request->user(),
            'forPdf' => false,
        ]);
    }

    /** @param array<string, mixed> $filters */
    private function auditAppointmentsExport(string $action, string $format, int $clinicId, array $filters, array $metrics): void
    {
        AuditLogger::log($action, 'reports', null, [], [
            'date_from' => $filters['start_date'] ?? null,
            'date_to' => $filters['end_date'] ?? null,
            'filters' => $filters,
            'user_id' => auth()->id(),
            'clinic_id' => $clinicId,
            'total_records' => $metrics['total'] ?? 0,
            'format' => $format,
        ], 'Reporte de citas exportado.');
    }

    /** @param array<string, mixed> $filters */
    private function auditClinicalExport(string $action, string $format, int $clinicId, array $filters, array $metrics): void
    {
        AuditLogger::log($action, 'reports', null, [], [
            'date_from' => $filters['start_date'] ?? null,
            'date_to' => $filters['end_date'] ?? null,
            'filters' => $filters,
            'user_id' => auth()->id(),
            'clinic_id' => $clinicId,
            'total_records' => $metrics['consultations'] ?? 0,
            'format' => $format,
        ], 'Reporte clínico exportado.');
    }
}
