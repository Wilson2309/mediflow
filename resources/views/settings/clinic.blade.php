<x-app-layout>
    <div class="space-y-6">
        <section class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-[#2563EB]">Configuración</p>
                <h1 class="mt-2 text-2xl font-bold tracking-tight text-[#0F172A] sm:text-3xl">Configuración del consultorio</h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-[#475569]">Administra la identidad y los datos principales que MediFlow utilizará en futuras recetas, reportes, correos y documentos.</p>
            </div>
            <span class="inline-flex rounded-full px-3 py-1.5 text-sm font-bold {{ $clinic->status === 'active' ? 'bg-[#10B981]/10 text-[#047857]' : 'bg-[#EF4444]/10 text-[#EF4444]' }}">{{ $clinic->status === 'active' ? 'Consultorio activo' : 'Consultorio inactivo' }}</span>
        </section>

        @if (session('success'))
            <div class="rounded-lg border border-[#10B981]/20 bg-[#10B981]/10 px-4 py-3 text-sm font-semibold text-[#047857]">{{ session('success') }}</div>
        @endif

        <section class="grid gap-6 xl:grid-cols-3">
            <div class="space-y-6 xl:col-span-2">
                <form method="POST" action="{{ route('settings.clinic.update') }}" class="rounded-lg border border-[#E2E8F0] bg-white shadow-sm">
                    @csrf
                    @method('PUT')
                    <div class="border-b border-[#E2E8F0] px-5 py-4">
                        <h2 class="text-base font-bold text-[#0F172A]">Datos del consultorio</h2>
                        <p class="mt-1 text-sm text-[#475569]">Información administrativa y de contacto de la clínica actual.</p>
                    </div>
                    <div class="grid gap-5 p-5 md:grid-cols-2">
                        <div class="md:col-span-2">
                            <label for="name" class="mb-2 block text-sm font-semibold text-[#0F172A]">Nombre del consultorio *</label>
                            <input id="name" name="name" type="text" maxlength="255" required value="{{ old('name', $clinic->name) }}" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
                            @error('name')<p class="mt-2 text-sm text-[#EF4444]">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="ruc" class="mb-2 block text-sm font-semibold text-[#0F172A]">RUC</label>
                            <input id="ruc" name="ruc" type="text" maxlength="20" value="{{ old('ruc', $clinic->ruc) }}" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
                            @error('ruc')<p class="mt-2 text-sm text-[#EF4444]">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="phone" class="mb-2 block text-sm font-semibold text-[#0F172A]">Teléfono</label>
                            <input id="phone" name="phone" type="text" maxlength="30" value="{{ old('phone', $clinic->phone) }}" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
                            @error('phone')<p class="mt-2 text-sm text-[#EF4444]">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="email" class="mb-2 block text-sm font-semibold text-[#0F172A]">Correo</label>
                            <input id="email" name="email" type="email" maxlength="255" value="{{ old('email', $clinic->email) }}" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
                            @error('email')<p class="mt-2 text-sm text-[#EF4444]">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="status" class="mb-2 block text-sm font-semibold text-[#0F172A]">Estado *</label>
                            <select id="status" name="status" required class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
                                <option value="active" @selected(old('status', $clinic->status) === 'active')>Activo</option>
                                <option value="inactive" @selected(old('status', $clinic->status) === 'inactive')>Inactivo</option>
                            </select>
                            @error('status')<p class="mt-2 text-sm text-[#EF4444]">{{ $message }}</p>@enderror
                        </div>
                        <div class="md:col-span-2">
                            <label for="address" class="mb-2 block text-sm font-semibold text-[#0F172A]">Dirección</label>
                            <input id="address" name="address" type="text" maxlength="255" value="{{ old('address', $clinic->address) }}" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
                            @error('address')<p class="mt-2 text-sm text-[#EF4444]">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <div class="flex justify-end border-t border-[#E2E8F0] px-5 py-4">
                        <button type="submit" class="rounded-lg bg-[#2563EB] px-5 py-3 text-sm font-semibold text-white shadow-sm shadow-blue-500/20 transition hover:bg-blue-700">Guardar configuración</button>
                    </div>
                </form>

                <article class="rounded-lg border border-dashed border-[#38BDF8]/50 bg-[#38BDF8]/5 p-5">
                    <h2 class="text-base font-bold text-[#0F172A]">Configuración avanzada</h2>
                    <p class="mt-2 text-sm leading-6 text-[#475569]">Esta sección queda preparada para incorporar logo, firma médica, encabezados de recetas, datos legales, correo, moneda, horarios, impuestos y preferencias del sistema.</p>
                    <span class="mt-4 inline-flex rounded-full bg-white px-3 py-1 text-xs font-bold text-[#2563EB]">Próxima fase</span>
                </article>
            </div>

            <aside class="space-y-6">
                <article class="rounded-lg border border-[#E2E8F0] bg-white shadow-sm">
                    <div class="border-b border-[#E2E8F0] px-5 py-4"><h2 class="text-base font-bold text-[#0F172A]">Resumen operativo</h2></div>
                    <div class="grid grid-cols-2 gap-4 p-5">
                        <div class="rounded-lg bg-[#F8FAFC] p-4"><p class="text-xs font-bold uppercase text-[#475569]">Usuarios</p><p class="mt-2 text-2xl font-bold text-[#0F172A]">{{ number_format($clinic->users_count) }}</p></div>
                        <div class="rounded-lg bg-[#F8FAFC] p-4"><p class="text-xs font-bold uppercase text-[#475569]">Médicos</p><p class="mt-2 text-2xl font-bold text-[#0F172A]">{{ number_format($clinic->doctors_count) }}</p></div>
                        <div class="rounded-lg bg-[#F8FAFC] p-4"><p class="text-xs font-bold uppercase text-[#475569]">Pacientes</p><p class="mt-2 text-2xl font-bold text-[#0F172A]">{{ number_format($clinic->patients_count) }}</p></div>
                        <div class="rounded-lg bg-[#F8FAFC] p-4"><p class="text-xs font-bold uppercase text-[#475569]">Servicios activos</p><p class="mt-2 text-2xl font-bold text-[#0F172A]">{{ number_format($clinic->active_services_count) }}</p></div>
                    </div>
                </article>
                <article class="rounded-lg border border-[#E2E8F0] bg-white shadow-sm">
                    <div class="border-b border-[#E2E8F0] px-5 py-4"><h2 class="text-base font-bold text-[#0F172A]">Información del registro</h2></div>
                    <div class="space-y-4 p-5"><div><p class="text-xs font-bold uppercase text-[#475569]">Fecha de creación</p><p class="mt-1 text-sm text-[#0F172A]">{{ $clinic->created_at?->format('d/m/Y H:i') }}</p></div><div><p class="text-xs font-bold uppercase text-[#475569]">Última actualización</p><p class="mt-1 text-sm text-[#0F172A]">{{ $clinic->updated_at?->format('d/m/Y H:i') }}</p></div></div>
                </article>
            </aside>
        </section>
    </div>
</x-app-layout>
