@php
    $service = $service ?? null;
    $selectedDoctorIds = collect(old('doctor_ids', $service?->doctors?->pluck('id')->all() ?? []))->map(fn ($id) => (int) $id)->all();
@endphp

<div class="grid gap-5 p-5 md:grid-cols-2">
    <div class="md:col-span-2">
        <label for="name" class="mb-2 block text-sm font-semibold text-[#0F172A]">Nombre *</label>
        <input id="name" name="name" type="text" maxlength="255" required value="{{ old('name', $service?->name) }}" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm text-[#0F172A] focus:border-[#2563EB] focus:ring-[#2563EB]">
        @error('name') <p class="mt-2 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
    </div>

    <div class="md:col-span-2">
        <label for="description" class="mb-2 block text-sm font-semibold text-[#0F172A]">Descripción</label>
        <textarea id="description" name="description" rows="4" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm text-[#0F172A] focus:border-[#2563EB] focus:ring-[#2563EB]">{{ old('description', $service?->description) }}</textarea>
        @error('description') <p class="mt-2 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="price" class="mb-2 block text-sm font-semibold text-[#0F172A]">Precio *</label>
        <div class="relative">
            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-sm font-semibold text-[#475569]">$</span>
            <input id="price" name="price" type="number" min="0" max="99999999.99" step="0.01" required value="{{ old('price', $service?->price ?? '0.00') }}" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] pl-8 text-sm text-[#0F172A] focus:border-[#2563EB] focus:ring-[#2563EB]">
        </div>
        @error('price') <p class="mt-2 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="duration_minutes" class="mb-2 block text-sm font-semibold text-[#0F172A]">Duración en minutos *</label>
        <input id="duration_minutes" name="duration_minutes" type="number" min="1" max="1440" step="1" required value="{{ old('duration_minutes', $service?->duration_minutes ?? 30) }}" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm text-[#0F172A] focus:border-[#2563EB] focus:ring-[#2563EB]">
        @error('duration_minutes') <p class="mt-2 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="status" class="mb-2 block text-sm font-semibold text-[#0F172A]">Estado *</label>
        <select id="status" name="status" required class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm text-[#0F172A] focus:border-[#2563EB] focus:ring-[#2563EB]">
            <option value="active" @selected(old('status', $service?->status ?? 'active') === 'active')>Activo</option>
            <option value="inactive" @selected(old('status', $service?->status) === 'inactive')>Inactivo</option>
        </select>
        @error('status') <p class="mt-2 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
    </div>

    <div class="md:col-span-2">
        <label class="mb-2 block text-sm font-semibold text-[#0F172A]">Médicos que ofrecen este servicio</label>
        <div class="grid gap-2 rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] p-3 md:grid-cols-2">
            @forelse ($doctors as $doctor)
                <label class="flex items-start gap-3 rounded-md bg-white p-3 text-sm text-[#0F172A] shadow-sm">
                    <input type="checkbox" name="doctor_ids[]" value="{{ $doctor->id }}" @checked(in_array((int) $doctor->id, $selectedDoctorIds, true)) class="mt-1 rounded border-[#E2E8F0] text-[#2563EB] focus:ring-[#2563EB]">
                    <span>
                        <span class="block font-semibold">{{ $doctor->user?->name ?? 'Médico sin usuario' }}</span>
                        <span class="block text-xs text-[#475569]">{{ $doctor->specialty?->name ?? 'Sin especialidad' }}{{ $doctor->license_number ? ' · '.$doctor->license_number : '' }}</span>
                    </span>
                </label>
            @empty
                <p class="text-sm text-[#475569]">No hay médicos activos para asignar.</p>
            @endforelse
        </div>
        @error('doctor_ids') <p class="mt-2 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
        @error('doctor_ids.*') <p class="mt-2 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
    </div>
</div>
