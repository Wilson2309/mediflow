<?php

use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AssistantMessageController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\ClinicSettingsController;
use App\Http\Controllers\ConsultationController;
use App\Http\Controllers\DailyAgendaController;
use App\Http\Controllers\DoctorController;
use App\Http\Controllers\DemoRequestController;
use App\Http\Controllers\FinancialAuditController;
use App\Http\Controllers\HealthCheckController;
use App\Http\Controllers\MedicalRecordController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PrescriptionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicDemoRequestController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\SwitchClinicController;
use App\Http\Controllers\UserController;
use App\Models\Appointment;
use App\Models\Consultation;
use App\Models\Doctor;
use App\Models\DemoRequest;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Prescription;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/app-health', [HealthCheckController::class, 'app'])->name('app-health');
Route::get('/internet-health', [HealthCheckController::class, 'internet'])->name('internet-health');

Route::post('/demo-requests', [PublicDemoRequestController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('demo-requests.store');

Route::get('/verificar-receta/{code}', [PrescriptionController::class, 'verify'])
    ->name('prescriptions.verify');

Route::get('/dashboard', function () {
    $user = auth()->user();
    $clinicId = $user?->activeClinicId();
    $isDoctorDashboard = (bool) $user?->hasRole('medico');
    $doctor = $isDoctorDashboard && $clinicId
        ? Doctor::where('clinic_id', $clinicId)->where('user_id', $user->id)->first()
        : null;
    $canViewFinance = $user?->canAny(['payments.view', 'reports.financial']);
    $localNow = now(config('app.timezone', 'America/Guayaquil'));
    $localToday = $localNow->toDateString();

    $patientCount = $clinicId && $user?->can('patients.view')
        ? Patient::where('clinic_id', $clinicId)
            ->when($isDoctorDashboard, function ($query) use ($doctor) {
                if (! $doctor) {
                    $query->whereRaw('1 = 0');
                    return;
                }

                $query->where(function ($query) use ($doctor) {
                    $query->whereHas('appointments', fn ($query) => $query->where('doctor_id', $doctor->id))
                        ->orWhereHas('consultations', fn ($query) => $query->where('doctor_id', $doctor->id));
                });
            })
            ->count()
        : 0;
    $activeDoctorCount = $clinicId && $user?->can('doctors.view')
        ? Doctor::where('clinic_id', $clinicId)->where('status', 'active')->count()
        : 0;
    $todayAppointmentCount = $clinicId && $user?->can('appointments.view')
        ? Appointment::where('clinic_id', $clinicId)
            ->when($isDoctorDashboard, function ($query) use ($doctor) {
                $doctor ? $query->where('doctor_id', $doctor->id) : $query->whereRaw('1 = 0');
            })
            ->whereDate('appointment_date', $localToday)
            ->whereIn('status', ['scheduled', 'confirmed'])
            ->count()
        : 0;
    $consultationCount = $clinicId && $user?->can('consultations.view')
        ? Consultation::whereHas('patient', fn ($query) => $query->where('clinic_id', $clinicId))
            ->when($isDoctorDashboard, function ($query) use ($doctor) {
                $doctor ? $query->where('doctor_id', $doctor->id) : $query->whereRaw('1 = 0');
            })
            ->count()
        : 0;
    $activePrescriptionCount = $clinicId && $user?->can('prescriptions.view')
        ? Prescription::whereHas('patient', fn ($query) => $query->where('clinic_id', $clinicId))
            ->when($isDoctorDashboard, function ($query) use ($doctor) {
                $doctor ? $query->where('doctor_id', $doctor->id) : $query->whereRaw('1 = 0');
            })
            ->where('status', 'active')
            ->count()
        : 0;
    $monthlyPaidIncome = $clinicId && $canViewFinance
        ? Payment::where('clinic_id', $clinicId)
            ->where('payment_status', 'paid')
            ->whereMonth('payment_date', $localNow->month)
            ->whereYear('payment_date', $localNow->year)
            ->sum('amount')
        : 0;
    $pendingPaymentsCount = $clinicId && $canViewFinance
        ? Payment::where('clinic_id', $clinicId)->where('payment_status', 'pending')->count()
        : 0;
    $activeServiceCount = $clinicId && $user?->can('services.view')
        ? Service::where('clinic_id', $clinicId)->where('status', 'active')->count()
        : 0;
    $activeUserCount = $clinicId && $user?->can('users.view')
        ? User::where('clinic_id', $clinicId)->where('status', 'active')->count()
        : 0;
    $pendingDemoRequestCount = $user?->can('demo_requests.view')
        ? DemoRequest::pending()->count()
        : 0;
    $upcomingAppointments = $clinicId && $user?->can('appointments.view')
        ? Appointment::with(['patient', 'doctor.user', 'service'])
            ->where('clinic_id', $clinicId)
            ->when($isDoctorDashboard, function ($query) use ($doctor) {
                $doctor ? $query->where('doctor_id', $doctor->id) : $query->whereRaw('1 = 0');
            })
            ->whereDate('appointment_date', '>=', $localToday)
            ->whereIn('status', ['scheduled', 'confirmed'])
            ->orderBy('appointment_date')
            ->orderBy('start_time')
            ->limit(5)
            ->get()
        : collect();

    return view('dashboard', [
        'patientCount' => $patientCount,
        'activeDoctorCount' => $activeDoctorCount,
        'todayAppointmentCount' => $todayAppointmentCount,
        'consultationCount' => $consultationCount,
        'activePrescriptionCount' => $activePrescriptionCount,
        'monthlyPaidIncome' => $monthlyPaidIncome,
        'pendingPaymentsCount' => $pendingPaymentsCount,
        'activeServiceCount' => $activeServiceCount,
        'activeUserCount' => $activeUserCount,
        'pendingDemoRequestCount' => $pendingDemoRequestCount,
        'upcomingAppointments' => $upcomingAppointments,
        'isDoctorDashboard' => $isDoctorDashboard,
    ]);
})->middleware(['auth', 'verified', 'active_clinic', 'permission:dashboard.view'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::post('assistant/message', AssistantMessageController::class)
        ->middleware(['active_clinic', 'throttle:assistant'])
        ->name('assistant.message');

    $protectedResource = static function (string $uri, string $controller, string $permission): void {
        Route::resource($uri, $controller)->only(['create', 'store'])->middleware(['active_clinic', "permission:{$permission}.create"]);
        Route::resource($uri, $controller)->only(['index', 'show'])->middleware(['active_clinic', "permission:{$permission}.view"]);
        Route::resource($uri, $controller)->only(['edit', 'update'])->middleware(['active_clinic', "permission:{$permission}.update"]);
        Route::resource($uri, $controller)->only('destroy')->middleware(['active_clinic', "permission:{$permission}.delete"]);
    };

    $protectedResource('patients', PatientController::class, 'patients');
    $protectedResource('doctors', DoctorController::class, 'doctors');
    Route::get('daily-agenda', [DailyAgendaController::class, 'index'])->middleware(['active_clinic', 'permission:appointments.view'])->name('daily-agenda.index');
    Route::patch('daily-agenda/appointments/{appointment}/cancel', [DailyAgendaController::class, 'cancel'])->middleware(['active_clinic', 'permission:appointments.update'])->name('daily-agenda.appointments.cancel');
    Route::patch('daily-agenda/appointments/{appointment}/no-show', [DailyAgendaController::class, 'markNoShow'])->middleware(['active_clinic', 'permission:appointments.update'])->name('daily-agenda.appointments.no-show');
    Route::get('appointments/patients/search', [AppointmentController::class, 'searchPatients'])->middleware(['active_clinic', 'permission:appointments.create'])->name('appointments.patients.search');
    Route::get('appointments/doctors/search', [AppointmentController::class, 'searchDoctors'])->middleware(['active_clinic', 'permission:appointments.create'])->name('appointments.doctors.search');
    Route::get('appointments/availability', [AppointmentController::class, 'availability'])->middleware(['active_clinic', 'permission:appointments.create'])->name('appointments.availability');
    $protectedResource('appointments', AppointmentController::class, 'appointments');
    $protectedResource('consultations', ConsultationController::class, 'consultations');
    $protectedResource('medical-records', MedicalRecordController::class, 'medical_records');
    Route::get('prescriptions/{prescription}/print', [PrescriptionController::class, 'print'])->middleware(['active_clinic', 'permission:prescriptions.view'])->name('prescriptions.print');
    Route::get('prescriptions/{prescription}/pdf', [PrescriptionController::class, 'pdf'])->middleware(['active_clinic', 'permission:prescriptions.view'])->name('prescriptions.pdf');
    Route::post('prescriptions/{prescription}/send-email', [PrescriptionController::class, 'sendEmail'])->middleware(['active_clinic', 'permission:prescriptions.update'])->name('prescriptions.send-email');
    Route::post('prescriptions/{prescription}/sign', [PrescriptionController::class, 'sign'])->middleware(['active_clinic', 'permission:prescriptions.update'])->name('prescriptions.sign');
    $protectedResource('prescriptions', PrescriptionController::class, 'prescriptions');
    Route::get('payments/{payment}/receipt', [PaymentController::class, 'receipt'])->middleware(['active_clinic', 'permission:payments.view'])->name('payments.receipt');
    Route::get('payments/{payment}/receipt/print', [PaymentController::class, 'receiptPrint'])->middleware(['active_clinic', 'permission:payments.view'])->name('payments.receipt.print');
    $protectedResource('payments', PaymentController::class, 'payments');
    $protectedResource('services', ServiceController::class, 'services');
    $protectedResource('users', UserController::class, 'users');

    Route::get('demo-requests', [DemoRequestController::class, 'index'])->middleware('permission:demo_requests.view')->name('demo-requests.index');
    Route::get('demo-requests/{demo_request}', [DemoRequestController::class, 'show'])->middleware('permission:demo_requests.view')->name('demo-requests.show');
    Route::patch('demo-requests/{demo_request}', [DemoRequestController::class, 'update'])->middleware('permission:demo_requests.update')->name('demo-requests.update');

    Route::get('reports', [ReportController::class, 'index'])->middleware(['active_clinic', 'permission:reports.view'])->name('reports.index');
    Route::get('reports/appointments', [ReportController::class, 'appointments'])->middleware(['active_clinic', 'permission:reports.appointments'])->name('reports.appointments');
    Route::get('reports/appointments/export/pdf', [ReportController::class, 'appointmentsPdf'])->middleware(['active_clinic', 'permission:reports.appointments'])->name('reports.appointments.export.pdf');
    Route::get('reports/appointments/export/csv', [ReportController::class, 'appointmentsCsv'])->middleware(['active_clinic', 'permission:reports.appointments'])->name('reports.appointments.export.csv');
    Route::get('reports/appointments/export/xlsx', [ReportController::class, 'appointmentsXlsx'])->middleware(['active_clinic', 'permission:reports.appointments'])->name('reports.appointments.export.xlsx');
    Route::get('reports/appointments/print', [ReportController::class, 'appointmentsPrint'])->middleware(['active_clinic', 'permission:reports.appointments'])->name('reports.appointments.print');
    Route::get('reports/clinical', [ReportController::class, 'clinical'])->middleware(['active_clinic', 'permission:reports.clinical'])->name('reports.clinical');
    Route::get('reports/clinical/export/pdf', [ReportController::class, 'clinicalPdf'])->middleware(['active_clinic', 'permission:reports.clinical'])->name('reports.clinical.export.pdf');
    Route::get('reports/clinical/export/csv', [ReportController::class, 'clinicalCsv'])->middleware(['active_clinic', 'permission:reports.clinical'])->name('reports.clinical.export.csv');
    Route::get('reports/clinical/export/xlsx', [ReportController::class, 'clinicalXlsx'])->middleware(['active_clinic', 'permission:reports.clinical'])->name('reports.clinical.export.xlsx');
    Route::get('reports/clinical/print', [ReportController::class, 'clinicalPrint'])->middleware(['active_clinic', 'permission:reports.clinical'])->name('reports.clinical.print');
    Route::get('reports/financial', [ReportController::class, 'financial'])->middleware(['active_clinic', 'permission:reports.financial'])->name('reports.financial');
    Route::get('reports/financial/export/pdf', [ReportController::class, 'financialPdf'])->middleware(['active_clinic', 'permission:reports.financial'])->name('reports.financial.export.pdf');
    Route::get('reports/financial/export/csv', [ReportController::class, 'financialCsv'])->middleware(['active_clinic', 'permission:reports.financial'])->name('reports.financial.export.csv');
    Route::get('reports/financial/export/xlsx', [ReportController::class, 'financialXlsx'])->middleware(['active_clinic', 'permission:reports.financial'])->name('reports.financial.export.xlsx');
    Route::get('reports/financial/print', [ReportController::class, 'financialPrint'])->middleware(['active_clinic', 'permission:reports.financial'])->name('reports.financial.print');
    Route::get('financial-audit', [FinancialAuditController::class, 'index'])->middleware(['active_clinic', 'permission:reports.financial'])->name('financial-audit.index');
    Route::get('reports/patients', [ReportController::class, 'patients'])->middleware(['active_clinic', 'permission:reports.patients'])->name('reports.patients');
    Route::get('reports/doctors', [ReportController::class, 'doctors'])->middleware(['active_clinic', 'permission:reports.doctors'])->name('reports.doctors');
    Route::get('reports/services', [ReportController::class, 'services'])->middleware(['active_clinic', 'permission:reports.services'])->name('reports.services');
    Route::get('audit-logs', [AuditLogController::class, 'index'])->middleware(['active_clinic', 'permission:audit_logs.view'])->name('audit-logs.index');
    Route::get('settings/clinic', [ClinicSettingsController::class, 'edit'])->middleware(['active_clinic', 'permission:settings.clinic.view'])->name('settings.clinic.edit');
    Route::match(['put', 'patch'], 'settings/clinic', [ClinicSettingsController::class, 'update'])->middleware(['active_clinic', 'permission:settings.clinic.update'])->name('settings.clinic.update');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Switch Clinic (Tenant Switcher)
    Route::post('/switch-clinic/{clinic}', SwitchClinicController::class)->name('switch-clinic');

    // Super Admin Routes
    Route::middleware('permission:super_admin.access')->prefix('super-admin')->name('super-admin.')->group(function () {
        Route::get('clinics', [\App\Http\Controllers\SuperAdmin\ClinicController::class, 'index'])->name('clinics.index');
        Route::get('clinics/create', [\App\Http\Controllers\SuperAdmin\ClinicController::class, 'create'])->name('clinics.create');
        Route::post('clinics', [\App\Http\Controllers\SuperAdmin\ClinicController::class, 'store'])->name('clinics.store');
        Route::get('clinics/{clinic}/edit', [\App\Http\Controllers\SuperAdmin\ClinicController::class, 'edit'])->name('clinics.edit');
        Route::match(['put', 'patch'], 'clinics/{clinic}', [\App\Http\Controllers\SuperAdmin\ClinicController::class, 'update'])->name('clinics.update');
    });
});

require __DIR__.'/auth.php';
