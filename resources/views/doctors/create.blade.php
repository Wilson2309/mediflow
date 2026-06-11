<x-app-layout>
    <div class="space-y-6">
        <section class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-[#2563EB]">Medicos</p>
                <h1 class="mt-2 text-2xl font-bold tracking-tight text-[#0F172A] sm:text-3xl">Nuevo medico</h1>
                <p class="mt-2 text-sm text-[#475569]">Crea el acceso del usuario medico y su perfil profesional.</p>
            </div>

            <a href="{{ route('doctors.index') }}" class="inline-flex items-center justify-center rounded-lg border border-[#E2E8F0] px-4 py-3 text-sm font-semibold text-[#475569] transition hover:border-[#2563EB] hover:text-[#2563EB]">
                Volver
            </a>
        </section>

        <form method="POST" action="{{ route('doctors.store') }}" class="space-y-6">
            @csrf

            <section class="rounded-lg border border-[#E2E8F0] bg-white shadow-sm">
                <div class="border-b border-[#E2E8F0] px-5 py-4">
                    <h2 class="text-base font-bold text-[#0F172A]">Datos de acceso</h2>
                </div>

                <div class="grid gap-5 p-5 md:grid-cols-2">
                    <div>
                        <label for="name" class="mb-2 block text-sm font-semibold text-[#0F172A]">Nombre *</label>
                        <input id="name" name="name" type="text" value="{{ old('name') }}" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
                        @error('name') <p class="mt-2 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="email" class="mb-2 block text-sm font-semibold text-[#0F172A]">Email *</label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
                        @error('email') <p class="mt-2 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="password" class="mb-2 block text-sm font-semibold text-[#0F172A]">Password *</label>
                        <input id="password" name="password" type="password" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
                        @error('password') <p class="mt-2 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="password_confirmation" class="mb-2 block text-sm font-semibold text-[#0F172A]">Confirmar password *</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
                    </div>
                </div>
            </section>

            <section class="rounded-lg border border-[#E2E8F0] bg-white shadow-sm">
                <div class="border-b border-[#E2E8F0] px-5 py-4">
                    <h2 class="text-base font-bold text-[#0F172A]">Datos profesionales</h2>
                </div>

                <div class="grid gap-5 p-5 md:grid-cols-2">
                    <div>
                        <label for="specialty_id" class="mb-2 block text-sm font-semibold text-[#0F172A]">Especialidad</label>
                        <select id="specialty_id" name="specialty_id" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
                            <option value="">Sin especialidad</option>
                            @foreach ($specialties as $specialty)
                                <option value="{{ $specialty->id }}" @selected((string) old('specialty_id') === (string) $specialty->id)>{{ $specialty->name }}</option>
                            @endforeach
                        </select>
                        @error('specialty_id') <p class="mt-2 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="license_number" class="mb-2 block text-sm font-semibold text-[#0F172A]">Licencia profesional</label>
                        <input id="license_number" name="license_number" type="text" value="{{ old('license_number') }}" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
                        @error('license_number') <p class="mt-2 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="phone" class="mb-2 block text-sm font-semibold text-[#0F172A]">Telefono</label>
                        <input id="phone" name="phone" type="text" value="{{ old('phone') }}" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
                        @error('phone') <p class="mt-2 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="consultation_fee" class="mb-2 block text-sm font-semibold text-[#0F172A]">Tarifa de consulta *</label>
                        <input id="consultation_fee" name="consultation_fee" type="number" step="0.01" min="0" value="{{ old('consultation_fee', '0.00') }}" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
                        @error('consultation_fee') <p class="mt-2 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="status" class="mb-2 block text-sm font-semibold text-[#0F172A]">Estado *</label>
                        <select id="status" name="status" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
                            <option value="active" @selected(old('status', 'active') === 'active')>Activo</option>
                            <option value="inactive" @selected(old('status') === 'inactive')>Inactivo</option>
                        </select>
                        @error('status') <p class="mt-2 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="flex flex-col-reverse gap-3 border-t border-[#E2E8F0] px-5 py-4 sm:flex-row sm:justify-end">
                    <a href="{{ route('doctors.index') }}" class="inline-flex items-center justify-center rounded-lg border border-[#E2E8F0] px-4 py-3 text-sm font-semibold text-[#475569] transition hover:border-[#2563EB] hover:text-[#2563EB]">Cancelar</a>
                    <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-[#2563EB] px-4 py-3 text-sm font-semibold text-white shadow-sm shadow-blue-500/20 transition hover:bg-blue-700">Guardar medico</button>
                </div>
            </section>
        </form>
    </div>
</x-app-layout>
