<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Doctor;
use App\Models\Specialty;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    private const ROLES = [
        'administrador' => 'Administrador',
        'medico' => 'MÃ©dico',
        'recepcionista' => 'Recepcionista',
        'caja_finanzas' => 'Caja / Finanzas',
    ];

    public function index(Request $request): View
    {
        $clinicId = $this->clinicId();
        $search = trim((string) $request->query('search'));
        $role = $request->query('role');
        $status = $request->query('status');

        $users = User::query()
            ->with(['clinic', 'roles'])
            ->where('clinic_id', $clinicId)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when(array_key_exists((string) $role, self::ROLES), fn ($query) => $query->whereHas('roles', fn ($query) => $query->where('name', $role)))
            ->when(in_array($status, ['active', 'inactive'], true), fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('users.index', [
            'users' => $users,
            'roles' => $this->roleOptions(),
            'search' => $search,
            'role' => $role,
            'status' => $status,
        ]);
    }

    public function create(): View
    {
        $this->clinicId();

        return view('users.create', [
            'roles' => $this->roleOptions(),
            'specialties' => $this->availableSpecialties(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $clinicId = $this->clinicId();
        $validated = $request->validated();
        $this->ensureRole($validated['role']);

        $createdUser = null;

        DB::transaction(function () use ($validated, $clinicId, &$createdUser) {
            $user = User::create([
                'clinic_id' => $clinicId,
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'password' => Hash::make($validated['password']),
                'status' => $validated['status'],
            ]);

            $user->syncRoles([$validated['role']]);
            $createdUser = $user;

            if ($validated['role'] === 'medico') {
                $this->createDoctorProfile($user, $validated, $clinicId);
            }
        });

        if ($createdUser) {
            AuditLogger::log('user.created', 'users', $createdUser, [], AuditLogger::modelSnapshot($createdUser, ['id', 'clinic_id', 'name', 'email', 'phone', 'status']), 'Usuario creado.');
        }

        return redirect()->route('users.index')->with('success', 'Usuario creado correctamente.');
    }

    public function show(User $user): View
    {
        $this->authorizeClinic($user);

        return view('users.show', [
            'managedUser' => $user->load(['clinic', 'roles', 'doctor.specialty']),
            'roles' => self::ROLES,
        ]);
    }

    public function edit(User $user): View
    {
        $this->authorizeClinic($user);

        return view('users.edit', [
            'managedUser' => $user->load(['roles', 'doctor.specialty']),
            'roles' => $this->roleOptions(),
            'specialties' => $this->availableSpecialties($user->doctor?->specialty_id),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->authorizeClinic($user);
        $validated = $request->validated();
        $currentRole = $user->getRoleNames()->first();

        $this->protectPrimaryAdministrator($user, $validated);

        if ($currentRole === 'administrador' && $validated['role'] !== 'administrador' && $this->administratorCount($user->clinic_id) <= 1) {
            throw ValidationException::withMessages(['role' => 'No puedes cambiar el rol del Ãºltimo administrador de la clÃ­nica.']);
        }

        if ($currentRole === 'administrador' && $user->status === 'active' && $validated['status'] === 'inactive' && $this->administratorCount($user->clinic_id, activeOnly: true) <= 1) {
            throw ValidationException::withMessages(['status' => 'No puedes inactivar al Ãºltimo administrador activo de la clÃ­nica.']);
        }

        $this->ensureRole($validated['role']);

        $old = AuditLogger::modelSnapshot($user, ['id', 'clinic_id', 'name', 'email', 'phone', 'status']);
        $old['role'] = $currentRole;

        DB::transaction(function () use ($user, $validated) {
            $data = [
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'status' => $validated['status'],
            ];

            if (! empty($validated['password'])) {
                $data['password'] = Hash::make($validated['password']);
            }

            $user->update($data);
            $user->syncRoles([$validated['role']]);

            if ($validated['role'] === 'medico') {
                $this->updateOrCreateDoctorProfile($user, $validated);
            } elseif ($user->doctor) {
                $user->doctor->update(['status' => 'inactive']);
            }
        });

        $user->refresh();
        $new = AuditLogger::modelSnapshot($user, ['id', 'clinic_id', 'name', 'email', 'phone', 'status']);
        $new['role'] = $user->getRoleNames()->first();
        AuditLogger::log($currentRole !== $new['role'] ? 'user.role_changed' : ($old['status'] === 'active' && $new['status'] === 'inactive' ? 'user.deactivated' : 'user.updated'), 'users', $user, $old, $new, 'Usuario actualizado.');

        return redirect()->route('users.show', $user)->with('success', 'Usuario actualizado correctamente.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->authorizeClinic($user);

        if ((int) $user->id === (int) auth()->id()) {
            throw ValidationException::withMessages(['user' => 'No puedes eliminar tu propio usuario.']);
        }

        if ($this->isPrimaryAdministrator($user)) {
            throw ValidationException::withMessages(['user' => 'El administrador principal de MediFlow no puede eliminarse.']);
        }

        if ($user->hasRole('administrador') && $this->administratorCount($user->clinic_id) <= 1) {
            throw ValidationException::withMessages(['user' => 'No puedes eliminar al Ãºltimo administrador de la clÃ­nica.']);
        }

        $old = AuditLogger::modelSnapshot($user, ['id', 'clinic_id', 'name', 'email', 'phone', 'status']);
        $old['role'] = $user->getRoleNames()->first();
        AuditLogger::log('user.deleted', 'users', $user, $old, [], 'Usuario eliminado.');

        DB::transaction(fn () => $user->delete());

        return redirect()->route('users.index')->with('success', 'Usuario eliminado correctamente.');
    }

    private function clinicId(): int
    {
        $clinicId = auth()->user()?->clinic_id;
        abort_if(! $clinicId, 403, 'El usuario autenticado no tiene una clÃ­nica asignada.');

        return (int) $clinicId;
    }

    private function authorizeClinic(User $user): void
    {
        abort_if((int) $user->clinic_id !== $this->clinicId(), 403);
    }

    /** @return array<string, string> */
    private function roleOptions(): array
    {
        foreach (array_keys(self::ROLES) as $role) {
            $this->ensureRole($role);
        }

        return self::ROLES;
    }

    private function ensureRole(string $role): void
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    }

    /** @return Collection<int, Specialty> */
    private function availableSpecialties(?int $currentSpecialtyId = null): Collection
    {
        return Specialty::query()
            ->where(function ($query) use ($currentSpecialtyId) {
                $query->where('status', 'active')
                    ->when($currentSpecialtyId, fn ($query) => $query->orWhereKey($currentSpecialtyId));
            })
            ->orderBy('name')
            ->get();
    }

    /** @param array<string, mixed> $validated */
    private function createDoctorProfile(User $user, array $validated, int $clinicId): Doctor
    {
        return Doctor::create([
            'clinic_id' => $clinicId,
            'user_id' => $user->id,
            ...$this->doctorData($validated),
        ]);
    }

    /** @param array<string, mixed> $validated */
    private function updateOrCreateDoctorProfile(User $user, array $validated): Doctor
    {
        $doctor = $user->doctor;

        if ($doctor) {
            abort_if((int) $doctor->clinic_id !== (int) $user->clinic_id, 403);
            $doctor->update($this->doctorData($validated));

            return $doctor;
        }

        return $this->createDoctorProfile($user, $validated, (int) $user->clinic_id);
    }

    /** @param array<string, mixed> $validated
     * @return array<string, mixed>
     */
    private function doctorData(array $validated): array
    {
        return [
            'specialty_id' => $validated['specialty_id'] ?? null,
            'license_number' => $validated['license_number'] ?? null,
            'phone' => $validated['doctor_phone'] ?? null,
            'consultation_fee' => $validated['consultation_fee'],
            'status' => $validated['doctor_status'],
        ];
    }

    private function administratorCount(int $clinicId, bool $activeOnly = false): int
    {
        return User::where('clinic_id', $clinicId)
            ->when($activeOnly, fn ($query) => $query->where('status', 'active'))
            ->whereHas('roles', fn ($query) => $query->where('name', 'administrador'))
            ->count();
    }

    /** @param array<string, mixed> $validated */
    private function protectPrimaryAdministrator(User $user, array $validated): void
    {
        if (! $this->isPrimaryAdministrator($user)) {
            return;
        }

        $errors = [];
        if ($validated['email'] !== $user->email) {
            $errors['email'] = 'El correo del administrador principal no puede cambiarse.';
        }
        if ($validated['role'] !== 'administrador') {
            $errors['role'] = 'El administrador principal debe conservar su rol.';
        }
        if ($validated['status'] !== 'active') {
            $errors['status'] = 'El administrador principal debe permanecer activo.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function isPrimaryAdministrator(User $user): bool
    {
        return strtolower($user->email) === 'admin@mediflow.com';
    }
}



