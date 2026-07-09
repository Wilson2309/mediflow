<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Clinic;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;

class ClinicController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->query('search');

        $clinics = Clinic::query()
            ->when($search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('ruc', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            })
            ->withCount('users')
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('super-admin.clinics.index', [
            'clinics' => $clinics,
        ]);
    }

    public function create(): View
    {
        return view('super-admin.clinics.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'ruc' => ['nullable', 'string', 'max:20'],
            'legal_representative' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'secondary_phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'country' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'clinic_type' => ['nullable', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'subscription_plan' => ['nullable', 'string', 'max:255'],
            'subscription_end_date' => ['nullable', 'date'],
            'internal_notes' => ['nullable', 'string'],
            'status' => ['required', 'string', Rule::in(['active', 'inactive'])],
            
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'admin_password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $data = $request->only([
            'name', 'legal_name', 'ruc', 'legal_representative', 'phone', 'secondary_phone',
            'email', 'website', 'address', 'country', 'state', 'city', 'clinic_type',
            'subscription_plan', 'subscription_end_date', 'internal_notes', 'status'
        ]);

        if (empty($data['subscription_plan'])) {
            $data['subscription_plan'] = 'basic';
        }

        if ($request->hasFile('logo')) {
            $data['logo_path'] = $request->file('logo')->store('logos', 'public');
        }

        DB::transaction(function () use ($data, $validated): void {
            $clinic = Clinic::create($data);

            $admin = User::create([
                'clinic_id' => $clinic->id,
                'current_clinic_id' => $clinic->id,
                'name' => $validated['admin_name'],
                'email' => $validated['admin_email'],
                'password' => Hash::make($validated['admin_password']),
                'status' => 'active',
            ]);

            $admin->assignRole('administrador');
            $admin->clinics()->sync([$clinic->id]);

            AuditLogger::log('superadmin.clinic_created', 'super-admin', $clinic, [], [
                'clinic_id' => $clinic->id,
                'name' => $clinic->name,
                'status' => $clinic->status,
                'admin_user_id' => $admin->id,
            ], 'Clinica creada por SuperAdmin.');

            if (($clinic->subscription_plan ?? null) || ($clinic->subscription_end_date ?? null)) {
                AuditLogger::log('superadmin.subscription_updated', 'super-admin', $clinic, [], [
                    'clinic_id' => $clinic->id,
                    'subscription_plan' => $clinic->subscription_plan,
                    'subscription_end_date' => $clinic->subscription_end_date?->toDateString(),
                ], 'Suscripcion configurada por SuperAdmin.');
            }
        });

        return redirect()->route('super-admin.clinics.index')
            ->with('status', 'Clínica y administrador creados exitosamente.');
    }

    public function edit(Clinic $clinic): View
    {
        return view('super-admin.clinics.edit', [
            'clinic' => $clinic,
        ]);
    }

    public function update(Request $request, Clinic $clinic): RedirectResponse
    {
        $old = AuditLogger::modelSnapshot($clinic);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'ruc' => ['nullable', 'string', 'max:20'],
            'legal_representative' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'secondary_phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'country' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'clinic_type' => ['nullable', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'subscription_plan' => ['nullable', 'string', 'max:255'],
            'subscription_end_date' => ['nullable', 'date'],
            'internal_notes' => ['nullable', 'string'],
            'status' => ['required', 'string', Rule::in(['active', 'inactive'])],
        ]);

        $data = $request->only([
            'name', 'legal_name', 'ruc', 'legal_representative', 'phone', 'secondary_phone',
            'email', 'website', 'address', 'country', 'state', 'city', 'clinic_type',
            'subscription_plan', 'subscription_end_date', 'internal_notes', 'status'
        ]);

        if (empty($data['subscription_plan'])) {
            $data['subscription_plan'] = 'basic';
        }

        if ($request->hasFile('logo')) {
            if ($clinic->logo_path) {
                Storage::disk('public')->delete($clinic->logo_path);
            }
            $data['logo_path'] = $request->file('logo')->store('logos', 'public');
        }

        $clinic->update($data);

        AuditLogger::log('superadmin.subscription_updated', 'super-admin', $clinic, $old, [
            ...AuditLogger::modelSnapshot($clinic),
            'clinic_id' => $clinic->id,
        ], 'Clinica actualizada por SuperAdmin.');

        return redirect()->route('super-admin.clinics.index')
            ->with('status', 'Clínica actualizada exitosamente.');
    }
}
