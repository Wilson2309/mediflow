<?php

use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\ConsultationController;
use App\Http\Controllers\ClinicSettingsController;
use App\Http\Controllers\DoctorController;
use App\Http\Controllers\MedicalRecordController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PrescriptionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\UserController;
use App\Models\Doctor;
use App\Models\Appointment;
use App\Models\Consultation;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Prescription;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    $clinicId = auth()->user()?->clinic_id;
    $patientCount = $clinicId ? Patient::where('clinic_id', $clinicId)->count() : 0;
    $activeDoctorCount = $clinicId
        ? Doctor::where('clinic_id', $clinicId)->where('status', 'active')->count()
        : 0;
    $todayAppointmentCount = $clinicId
        ? Appointment::where('clinic_id', $clinicId)
            ->whereDate('appointment_date', today())
            ->whereIn('status', ['scheduled', 'confirmed'])
            ->count()
        : 0;
    $consultationCount = $clinicId
        ? Consultation::whereHas('patient', fn ($query) => $query->where('clinic_id', $clinicId))->count()
        : 0;
    $activePrescriptionCount = $clinicId
        ? Prescription::whereHas('patient', fn ($query) => $query->where('clinic_id', $clinicId))->where('status', 'active')->count()
        : 0;
    $monthlyPaidIncome = $clinicId
        ? Payment::where('clinic_id', $clinicId)
            ->where('payment_status', 'paid')
            ->whereMonth('payment_date', now()->month)
            ->whereYear('payment_date', now()->year)
            ->sum('amount')
        : 0;
    $pendingPaymentsCount = $clinicId
        ? Payment::where('clinic_id', $clinicId)->where('payment_status', 'pending')->count()
        : 0;
    $activeServiceCount = $clinicId
        ? Service::where('clinic_id', $clinicId)->where('status', 'active')->count()
        : 0;
    $activeUserCount = $clinicId
        ? User::where('clinic_id', $clinicId)->where('status', 'active')->count()
        : 0;
    $upcomingAppointments = $clinicId
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
        'upcomingAppointments' => $upcomingAppointments,
    ]);
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::resource('patients', PatientController::class);
    Route::resource('doctors', DoctorController::class);
    Route::resource('appointments', AppointmentController::class);
    Route::resource('consultations', ConsultationController::class);
    Route::resource('medical-records', MedicalRecordController::class);
    Route::resource('prescriptions', PrescriptionController::class);
    Route::resource('payments', PaymentController::class);
    Route::resource('services', ServiceController::class);
    Route::resource('users', UserController::class);
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/appointments', [ReportController::class, 'appointments'])->name('reports.appointments');
    Route::get('reports/clinical', [ReportController::class, 'clinical'])->name('reports.clinical');
    Route::get('reports/financial', [ReportController::class, 'financial'])->name('reports.financial');
    Route::get('reports/patients', [ReportController::class, 'patients'])->name('reports.patients');
    Route::get('reports/doctors', [ReportController::class, 'doctors'])->name('reports.doctors');
    Route::get('reports/services', [ReportController::class, 'services'])->name('reports.services');
    Route::get('settings/clinic', [ClinicSettingsController::class, 'edit'])->name('settings.clinic.edit');
    Route::match(['put', 'patch'], 'settings/clinic', [ClinicSettingsController::class, 'update'])->name('settings.clinic.update');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
