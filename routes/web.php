<?php

use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\ClinicSettingsController;
use App\Http\Controllers\ConsultationController;
use App\Http\Controllers\DoctorController;
use App\Http\Controllers\DemoRequestController;
use App\Http\Controllers\MedicalRecordController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PrescriptionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicDemoRequestController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ServiceController;
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

Route::post('/demo-requests', [PublicDemoRequestController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('demo-requests.store');

Route::get('/verificar-receta/{code}', [PrescriptionController::class, 'verify'])
    ->name('prescriptions.verify');

Route::get('/dashboard', function () {
    $user = auth()->user();
    $clinicId = $user?->clinic_id;
    $canViewFinance = $user?->canAny(['payments.view', 'reports.financial']);
    $patientCount = $clinicId && $user?->can('patients.view') ? Patient::where('clinic_id', $clinicId)->count() : 0;
    $activeDoctorCount = $clinicId && $user?->can('doctors.view')
        ? Doctor::where('clinic_id', $clinicId)->where('status', 'active')->count()
        : 0;
    $todayAppointmentCount = $clinicId && $user?->can('appointments.view')
        ? Appointment::where('clinic_id', $clinicId)
            ->whereDate('appointment_date', today())
            ->whereIn('status', ['scheduled', 'confirmed'])
            ->count()
        : 0;
    $consultationCount = $clinicId && $user?->can('consultations.view')
        ? Consultation::whereHas('patient', fn ($query) => $query->where('clinic_id', $clinicId))->count()
        : 0;
    $activePrescriptionCount = $clinicId && $user?->can('prescriptions.view')
        ? Prescription::whereHas('patient', fn ($query) => $query->where('clinic_id', $clinicId))->where('status', 'active')->count()
        : 0;
    $monthlyPaidIncome = $clinicId && $canViewFinance
        ? Payment::where('clinic_id', $clinicId)
            ->where('payment_status', 'paid')
            ->whereMonth('payment_date', now()->month)
            ->whereYear('payment_date', now()->year)
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
            ->whereDate('appointment_date', '>=', today())
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
    ]);
})->middleware(['auth', 'verified', 'permission:dashboard.view'])->name('dashboard');

Route::middleware('auth')->group(function () {
    $protectedResource = static function (string $uri, string $controller, string $permission): void {
        Route::resource($uri, $controller)->only(['create', 'store'])->middleware("permission:{$permission}.create");
        Route::resource($uri, $controller)->only(['index', 'show'])->middleware("permission:{$permission}.view");
        Route::resource($uri, $controller)->only(['edit', 'update'])->middleware("permission:{$permission}.update");
        Route::resource($uri, $controller)->only('destroy')->middleware("permission:{$permission}.delete");
    };

    $protectedResource('patients', PatientController::class, 'patients');
    $protectedResource('doctors', DoctorController::class, 'doctors');
    $protectedResource('appointments', AppointmentController::class, 'appointments');
    $protectedResource('consultations', ConsultationController::class, 'consultations');
    $protectedResource('medical-records', MedicalRecordController::class, 'medical_records');
    Route::get('prescriptions/{prescription}/print', [PrescriptionController::class, 'print'])->middleware('permission:prescriptions.view')->name('prescriptions.print');
    Route::get('prescriptions/{prescription}/pdf', [PrescriptionController::class, 'pdf'])->middleware('permission:prescriptions.view')->name('prescriptions.pdf');
    Route::post('prescriptions/{prescription}/send-email', [PrescriptionController::class, 'sendEmail'])->middleware('permission:prescriptions.update')->name('prescriptions.send-email');
    Route::post('prescriptions/{prescription}/sign', [PrescriptionController::class, 'sign'])->middleware('permission:prescriptions.update')->name('prescriptions.sign');
    $protectedResource('prescriptions', PrescriptionController::class, 'prescriptions');
    $protectedResource('payments', PaymentController::class, 'payments');
    $protectedResource('services', ServiceController::class, 'services');
    $protectedResource('users', UserController::class, 'users');

    Route::get('demo-requests', [DemoRequestController::class, 'index'])->middleware('permission:demo_requests.view')->name('demo-requests.index');
    Route::get('demo-requests/{demo_request}', [DemoRequestController::class, 'show'])->middleware('permission:demo_requests.view')->name('demo-requests.show');
    Route::patch('demo-requests/{demo_request}', [DemoRequestController::class, 'update'])->middleware('permission:demo_requests.update')->name('demo-requests.update');

    Route::get('reports', [ReportController::class, 'index'])->middleware('permission:reports.view')->name('reports.index');
    Route::get('reports/appointments', [ReportController::class, 'appointments'])->middleware('permission:reports.appointments')->name('reports.appointments');
    Route::get('reports/clinical', [ReportController::class, 'clinical'])->middleware('permission:reports.clinical')->name('reports.clinical');
    Route::get('reports/financial', [ReportController::class, 'financial'])->middleware('permission:reports.financial')->name('reports.financial');
    Route::get('reports/patients', [ReportController::class, 'patients'])->middleware('permission:reports.patients')->name('reports.patients');
    Route::get('reports/doctors', [ReportController::class, 'doctors'])->middleware('permission:reports.doctors')->name('reports.doctors');
    Route::get('reports/services', [ReportController::class, 'services'])->middleware('permission:reports.services')->name('reports.services');
    Route::get('settings/clinic', [ClinicSettingsController::class, 'edit'])->middleware('permission:settings.clinic.view')->name('settings.clinic.edit');
    Route::match(['put', 'patch'], 'settings/clinic', [ClinicSettingsController::class, 'update'])->middleware('permission:settings.clinic.update')->name('settings.clinic.update');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
