<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesClinic;

use App\Http\Requests\StoreDoctorRequest;
use App\Http\Requests\UpdateDoctorRequest;
use App\Models\Doctor;
use App\Models\Specialty;
use App\Models\User;
use App\Support\ControlledResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class DoctorController extends Controller
{
    use ResolvesClinic;

    public function index(Request $request): View
    {
        $clinicId = $this->clinicId();
        $search = trim((string) $request->query('search'));
        $status = $request->query('status');
        $specialtyId = $request->query('specialty_id');

        $doctors = Doctor::query()
            ->with(['user', 'specialty'])
            ->where('clinic_id', $clinicId)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('license_number', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($query) use ($search) {
                            $query->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            })
            ->when(in_array($status, ['active', 'inactive'], true), fn ($query) => $query->where('status', $status))
            ->when($specialtyId, fn ($query) => $query->where('specialty_id', $specialtyId))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('doctors.index', [
            'doctors' => $doctors,
            'specialties' => $this->activeSpecialties(),
            'search' => $search,
            'status' => $status,
            'specialtyId' => $specialtyId,
        ]);
    }

    public function create(): View
    {
        $this->clinicId();

        return view('doctors.create', [
            'specialties' => $this->activeSpecialties(),
        ]);
    }

    public function store(StoreDoctorRequest $request): RedirectResponse
    {
        $clinicId = $this->clinicId();
        $validated = $request->validated();

        DB::transaction(function () use ($validated, $clinicId) {
            $user = User::create([
                'clinic_id' => $clinicId,
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
            ]);

            $user->assignRole('medico');

            Doctor::create([
                'clinic_id' => $clinicId,
                'user_id' => $user->id,
                'specialty_id' => $validated['specialty_id'] ?? null,
                'license_number' => $validated['license_number'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'consultation_fee' => $validated['consultation_fee'],
                'status' => $validated['status'],
            ]);
        });

        return redirect()
            ->route('doctors.index')
            ->with('success', 'Medico creado correctamente.');
    }

    public function show(Doctor $doctor): View
    {
        $this->authorizeClinic($doctor);

        return view('doctors.show', [
            'doctor' => $doctor->load(['user', 'specialty']),
        ]);
    }

    public function edit(Doctor $doctor): View
    {
        $this->authorizeClinic($doctor);

        return view('doctors.edit', [
            'doctor' => $doctor->load(['user', 'specialty']),
            'specialties' => $this->activeSpecialties(),
        ]);
    }

    public function update(UpdateDoctorRequest $request, Doctor $doctor): RedirectResponse
    {
        $this->authorizeClinic($doctor);

        $validated = $request->validated();

        DB::transaction(function () use ($doctor, $validated) {
            $userData = [
                'name' => $validated['name'],
                'email' => $validated['email'],
            ];

            if (! empty($validated['password'])) {
                $userData['password'] = Hash::make($validated['password']);
            }

            $doctor->user?->update($userData);

            $doctor->update([
                'specialty_id' => $validated['specialty_id'] ?? null,
                'license_number' => $validated['license_number'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'consultation_fee' => $validated['consultation_fee'],
                'status' => $validated['status'],
            ]);
        });

        return redirect()
            ->route('doctors.show', $doctor)
            ->with('success', 'Medico actualizado correctamente.');
    }

    public function destroy(Request $request, Doctor $doctor): RedirectResponse|JsonResponse
    {
        $outcome = DB::transaction(function () use ($doctor): string {
            $lockedDoctor = Doctor::query()->lockForUpdate()->findOrFail($doctor->getKey());
            $this->authorizeClinic($lockedDoctor);

            if ($lockedDoctor->appointments()->exists()
                || $lockedDoctor->consultations()->exists()
                || $lockedDoctor->prescriptions()->exists()) {
                return 'blocked';
            }

            $lockedDoctor->delete();

            return 'deleted';
        });

        if ($outcome === 'blocked') {
            if ($request->expectsJson()) {
                return ControlledResponse::jsonError(409, 'DOCTOR_REQUIRES_DEACTIVATION');
            }

            return back()->with('error', 'El médico no puede eliminarse. Inactívelo para conservar la trazabilidad.');
        }

        if ($request->expectsJson()) {
            return ControlledResponse::jsonSuccess('OPERATION_COMPLETED');
        }

        return redirect()
            ->route('doctors.index')
            ->with('success', 'Medico eliminado correctamente.');
    }


    private function authorizeClinic(Doctor $doctor): void
    {
        abort_if($doctor->clinic_id !== $this->clinicId(), 403);
    }

    private function activeSpecialties()
    {
        return Specialty::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
    }
}
