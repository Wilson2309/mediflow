@php
    $managedUser = $managedUser ?? null;
    $currentRole = old('role', $managedUser?->getRoleNames()->first());
@endphp

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
        <select id="role" name="role" required class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
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
