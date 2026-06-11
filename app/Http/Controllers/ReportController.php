<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportFilterRequest;
use App\Models\Appointment;
use App\Models\Consultation;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Prescription;
use App\Models\Service;
use App\Models\Specialty;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(ReportFilterRequest $request): View
    {
        [$clinicId, $filters, $start, $end] = $this->context($request);
        $user = $request->user();
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
                    ? Appointment::where('clinic_id', $clinicId)->whereDate('appointment_date', today())->count()
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
        $query = $this->appointmentsQuery($clinicId, $start, $end)
            ->when($filters['doctor_id'] ?? null, fn ($query, $id) => $query->where('doctor_id', $id))
            ->when($filters['service_id'] ?? null, fn ($query, $id) => $query->where('service_id', $id))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status));
        $total = (clone $query)->count();
        $statusCounts = (clone $query)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        return view('reports.appointments', [
            ...$this->shared($clinicId, $filters, $start, $end),
            'appointments' => (clone $query)->with(['patient', 'doctor.user', 'service'])->orderByDesc('appointment_date')->orderBy('start_time')->paginate(15)->withQueryString(),
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
        ]);
    }

    public function clinical(ReportFilterRequest $request): View
    {
        [$clinicId, $filters, $start, $end] = $this->context($request);
        $consultations = $this->consultationsQuery($clinicId, $start, $end)
            ->when($filters['doctor_id'] ?? null, fn ($query, $id) => $query->where('doctor_id', $id))
            ->when($filters['patient_id'] ?? null, fn ($query, $id) => $query->where('patient_id', $id));
        $prescriptions = $this->prescriptionsQuery($clinicId, $start, $end)
            ->when($filters['doctor_id'] ?? null, fn ($query, $id) => $query->where('doctor_id', $id))
            ->when($filters['patient_id'] ?? null, fn ($query, $id) => $query->where('patient_id', $id));
        $patients = Patient::where('clinic_id', $clinicId);

        return view('reports.clinical', [
            ...$this->shared($clinicId, $filters, $start, $end),
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
        ]);
    }

    public function financial(ReportFilterRequest $request): View
    {
        [$clinicId, $filters, $start, $end] = $this->context($request);
        $query = $this->paymentPeriod(Payment::where('clinic_id', $clinicId), $start, $end)
            ->when($filters['payment_status'] ?? null, fn ($query, $status) => $query->where('payment_status', $status))
            ->when($filters['payment_method'] ?? null, fn ($query, $method) => $query->where('payment_method', $method))
            ->when($filters['service_id'] ?? null, fn ($query, $id) => $query->where('service_id', $id));
        $paid = (clone $query)->where('payment_status', 'paid');

        return view('reports.financial', [
            ...$this->shared($clinicId, $filters, $start, $end),
            'payments' => (clone $query)->with(['patient', 'service'])->latest('created_at')->paginate(15)->withQueryString(),
            'metrics' => [
                'paidIncome' => (float) (clone $paid)->sum('amount'),
                'pending' => (clone $query)->where('payment_status', 'pending')->count(),
                'cancelled' => (clone $query)->where('payment_status', 'cancelled')->count(),
                'refunded' => (clone $query)->where('payment_status', 'refunded')->count(),
                'averagePayment' => (float) ((clone $paid)->avg('amount') ?? 0),
                'patientsWithPendingPayments' => (clone $query)->where('payment_status', 'pending')->distinct('patient_id')->count('patient_id'),
            ],
            'methodCounts' => (clone $query)->selectRaw('payment_method, COUNT(*) as total')->groupBy('payment_method')->pluck('total', 'payment_method'),
            'incomeByService' => (clone $paid)->whereNotNull('service_id')->with('service:id,name')->selectRaw('service_id, SUM(amount) as total')->groupBy('service_id')->orderByDesc('total')->limit(10)->get(),
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
        $clinicId = auth()->user()?->clinic_id;
        abort_if(! $clinicId, 403, 'El usuario autenticado no tiene una clínica asignada.');
        $filters = $request->validated();
        $start = Carbon::parse($filters['start_date'] ?? now()->startOfMonth()->toDateString())->startOfDay();
        $end = Carbon::parse($filters['end_date'] ?? now()->endOfMonth()->toDateString())->endOfDay();

        return [(int) $clinicId, $filters, $start, $end];
    }

    /** @return array<string, mixed> */
    private function shared(int $clinicId, array $filters, Carbon $start, Carbon $end): array
    {
        return [
            'filters' => $filters,
            'startDate' => $start->toDateString(),
            'endDate' => $end->toDateString(),
            'periodLabel' => $start->format('d/m/Y').' - '.$end->format('d/m/Y'),
            'doctors' => Doctor::with('user')->where('clinic_id', $clinicId)->orderBy('id')->get(),
            'services' => Service::where('clinic_id', $clinicId)->orderBy('name')->get(),
            'patientsList' => Patient::where('clinic_id', $clinicId)->orderBy('last_name')->orderBy('first_name')->get(),
            'specialties' => Specialty::whereHas('doctors', fn ($query) => $query->where('clinic_id', $clinicId))->orderBy('name')->get(),
        ];
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
}
