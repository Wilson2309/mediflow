@php
    $managedUser = $managedUser ?? null;
    $currentRole = old('role', $managedUser?->getRoleNames()->first());
    $doctor = $managedUser?->doctor;
@endphp

<div x-data="{ selectedRole: @js($currentRole) }">
<div class="grid gap-5 p-5 md:grid-cols-2">
    <div>
        <label for="name" class="mb-2 block text-sm font-semibold text-[#0F172A]">Nombre *</label>
        <input id="name" name="name" type="text" maxlength="255" required value="{{ old('name', $managedUser?->name) }}" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
        @error('name')<p class="mt-2 text-sm text-[#EF4444]">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="email" class="mb-2 block text-sm font-semibold text-[#0F172A]">Correo electrónico *</label>
        <input id="email" name="email" type="email" maxlength="255" required value="{{ old('email', $managedUser?->email) }}" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
        @error('email')<p class="mt-2 text-sm text-[#EF4444]">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="phone" class="mb-2 block text-sm font-semibold text-[#0F172A]">Teléfono</label>
        <input id="phone" name="phone" type="text" maxlength="30" value="{{ old('phone', $managedUser?->phone) }}" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
        @error('phone')<p class="mt-2 text-sm text-[#EF4444]">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="role" class="mb-2 block text-sm font-semibold text-[#0F172A]">Rol principal *</label>
        <select id="role" name="role" x-model="selectedRole" required class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
            <option value="">Selecciona un rol</option>
            @foreach ($roles as $value => $label)<option value="{{ $value }}" @selected($currentRole === $value)>{{ $label }}</option>@endforeach
        </select>
        @error('role')<p class="mt-2 text-sm text-[#EF4444]">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="password" class="mb-2 block text-sm font-semibold text-[#0F172A]">Contraseña {{ $managedUser ? '(opcional)' : '*' }}</label>
        <input id="password" name="password" type="password" {{ $managedUser ? '' : 'required' }} autocomplete="new-password" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
        @error('password')<p class="mt-2 text-sm text-[#EF4444]">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="password_confirmation" class="mb-2 block text-sm font-semibold text-[#0F172A]">Confirmar contraseña {{ $managedUser ? '(opcional)' : '*' }}</label>
        <input id="password_confirmation" name="password_confirmation" type="password" {{ $managedUser ? '' : 'required' }} autocomplete="new-password" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
    </div>
    <div>
        <label for="status" class="mb-2 block text-sm font-semibold text-[#0F172A]">Estado *</label>
        <select id="status" name="status" required class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
            <option value="active" @selected(old('status', $managedUser?->status ?? 'active') === 'active')>Activo</option>
            <option value="inactive" @selected(old('status', $managedUser?->status) === 'inactive')>Inactivo</option>
        </select>
        @error('status')<p class="mt-2 text-sm text-[#EF4444]">{{ $message }}</p>@enderror
    </div>
    <div>
        <p class="mb-2 text-sm font-semibold text-[#0F172A]">Clínica</p>
        <div class="rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-2.5 text-sm text-[#475569]">{{ auth()->user()->clinic?->name ?? 'Clínica asignada' }}</div>
        <p class="mt-2 text-xs text-[#475569]">Se asigna automáticamente desde tu usuario.</p>
    </div>
</div>

<section x-cloak x-show="selectedRole === 'medico'" x-transition class="border-t border-[#E2E8F0] bg-[#F8FAFC]/70 p-5">
    <div class="mb-5">
        <p class="text-sm font-semibold uppercase tracking-wide text-[#2563EB]">Perfil médico</p>
        <h2 class="mt-1 text-lg font-bold text-[#0F172A]">Información profesional</h2>
        <p class="mt-1 text-sm text-[#475569]">Estos datos crearán o actualizarán el perfil relacionado en el módulo Médicos.</p>
    </div>

    <div class="grid gap-5 md:grid-cols-2">
        <div>
            <label for="specialty_id" class="mb-2 block text-sm font-semibold text-[#0F172A]">Especialidad</label>
            <select id="specialty_id" name="specialty_id" class="w-full rounded-lg border-[#E2E8F0] bg-white text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
                <option value="">Sin especialidad asignada</option>
                @foreach ($specialties as $specialty)
                    <option value="{{ $specialty->id }}" @selected((string) old('specialty_id', $doctor?->specialty_id) === (string) $specialty->id)>{{ $specialty->name }}{{ $specialty->status === 'inactive' ? ' (inactiva)' : '' }}</option>
                @endforeach
            </select>
            @error('specialty_id')<p class="mt-2 text-sm text-[#EF4444]">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="license_number" class="mb-2 block text-sm font-semibold text-[#0F172A]">Número de licencia</label>
            <input id="license_number" name="license_number" type="text" maxlength="255" value="{{ old('license_number', $doctor?->license_number) }}" class="w-full rounded-lg border-[#E2E8F0] bg-white text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
            @error('license_number')<p class="mt-2 text-sm text-[#EF4444]">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="doctor_phone" class="mb-2 block text-sm font-semibold text-[#0F172A]">Teléfono profesional</label>
            <input id="doctor_phone" name="doctor_phone" type="text" maxlength="30" value="{{ old('doctor_phone', $doctor?->phone) }}" class="w-full rounded-lg border-[#E2E8F0] bg-white text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
            @error('doctor_phone')<p class="mt-2 text-sm text-[#EF4444]">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="consultation_fee" class="mb-2 block text-sm font-semibold text-[#0F172A]">Tarifa de consulta *</label>
            <input id="consultation_fee" name="consultation_fee" type="number" min="0" max="999999.99" step="0.01" :required="selectedRole === 'medico'" value="{{ old('consultation_fee', $doctor?->consultation_fee ?? '0.00') }}" class="w-full rounded-lg border-[#E2E8F0] bg-white text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
            @error('consultation_fee')<p class="mt-2 text-sm text-[#EF4444]">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="doctor_status" class="mb-2 block text-sm font-semibold text-[#0F172A]">Estado del perfil médico *</label>
            <select id="doctor_status" name="doctor_status" :required="selectedRole === 'medico'" class="w-full rounded-lg border-[#E2E8F0] bg-white text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
                <option value="active" @selected(old('doctor_status', $doctor?->status ?? 'active') === 'active')>Activo</option>
                <option value="inactive" @selected(old('doctor_status', $doctor?->status) === 'inactive')>Inactivo</option>
            </select>
            @error('doctor_status')<p class="mt-2 text-sm text-[#EF4444]">{{ $message }}</p>@enderror
        </div>
    </div>
</section>
</div>
