<x-app-layout>
    <div class="space-y-6">
        <section class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-[#2563EB]">Ficha del medico</p>
                <h1 class="mt-2 text-2xl font-bold tracking-tight text-[#0F172A] sm:text-3xl">{{ $doctor->user?->name ?? 'Usuario no asignado' }}</h1>
                <p class="mt-2 text-sm text-[#475569]">Perfil profesional registrado en MediFlow.</p>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row">
                <a href="{{ route('doctors.index') }}" class="inline-flex items-center justify-center rounded-lg border border-[#E2E8F0] px-4 py-3 text-sm font-semibold text-[#475569] transition hover:border-[#2563EB] hover:text-[#2563EB]">
                    Volver
                </a>
                @can('doctors.update')<a href="{{ route('doctors.edit', $doctor) }}" class="inline-flex items-center justify-center rounded-lg bg-[#2563EB] px-4 py-3 text-sm font-semibold text-white shadow-sm shadow-blue-500/20 transition hover:bg-blue-700">Editar medico</a>@endcan
            </div>
        </section>

        @if (session('success'))
            <div class="rounded-lg border border-[#10B981]/20 bg-[#10B981]/10 px-4 py-3 text-sm font-semibold text-[#047857]">
                {{ session('success') }}
            </div>
        @endif

        <section class="grid gap-6 xl:grid-cols-3">
            <div class="space-y-6 xl:col-span-2">
                <article class="rounded-lg border border-[#E2E8F0] bg-white shadow-sm">
                    <div class="border-b border-[#E2E8F0] px-5 py-4">
                        <h2 class="text-base font-bold text-[#0F172A]">Datos del usuario</h2>
                    </div>
                    <div class="grid gap-5 p-5 sm:grid-cols-2">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide text-[#475569]">Nombre</p>
                            <p class="mt-1 text-sm font-semibold text-[#0F172A]">{{ $doctor->user?->name ?? 'Sin registrar' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide text-[#475569]">Email</p>
                            <p class="mt-1 text-sm text-[#0F172A]">{{ $doctor->user?->email ?? 'Sin registrar' }}</p>
                        </div>
                    </div>
                </article>

                <article class="rounded-lg border border-[#E2E8F0] bg-white shadow-sm">
                    <div class="border-b border-[#E2E8F0] px-5 py-4">
                        <h2 class="text-base font-bold text-[#0F172A]">Datos profesionales</h2>
                    </div>
                    <div class="grid gap-5 p-5 sm:grid-cols-2">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide text-[#475569]">Licencia</p>
                            <p class="mt-1 text-sm text-[#0F172A]">{{ $doctor->license_number ?: 'Sin registrar' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide text-[#475569]">Telefono</p>
                            <p class="mt-1 text-sm text-[#0F172A]">{{ $doctor->phone ?: 'Sin registrar' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide text-[#475569]">Especialidad</p>
                            <p class="mt-1 text-sm text-[#0F172A]">{{ $doctor->specialty?->name ?? 'Sin especialidad' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide text-[#475569]">Tarifa de consulta</p>
                            <p class="mt-1 text-sm font-semibold text-[#0F172A]">${{ number_format((float) $doctor->consultation_fee, 2) }}</p>
                        </div>
                    </div>
                </article>

                <article class="rounded-lg border border-dashed border-[#38BDF8]/50 bg-[#38BDF8]/5 p-5">
                    <h2 class="text-base font-bold text-[#0F172A]">Agenda y consultas</h2>
                    <p class="mt-2 text-sm leading-6 text-[#475569]">Agenda y consultas disponibles en una proxima fase.</p>
                </article>
            </div>

            <aside class="space-y-6">
                <article class="rounded-lg border border-[#E2E8F0] bg-white shadow-sm">
                    <div class="border-b border-[#E2E8F0] px-5 py-4">
                        <h2 class="text-base font-bold text-[#0F172A]">Estado</h2>
                    </div>
                    <div class="p-5">
                        @if ($doctor->status === 'active')
                            <span class="inline-flex rounded-full border border-[#10B981]/20 bg-[#10B981]/10 px-3 py-1.5 text-sm font-bold text-[#10B981]">Activo</span>
                        @else
                            <span class="inline-flex rounded-full border border-slate-200 bg-slate-100 px-3 py-1.5 text-sm font-bold text-[#475569]">Inactivo</span>
                        @endif
                    </div>
                </article>

                <article class="rounded-lg border border-[#E2E8F0] bg-white shadow-sm">
                    <div class="border-b border-[#E2E8F0] px-5 py-4">
                        <h2 class="text-base font-bold text-[#0F172A]">Fechas</h2>
                    </div>
                    <div class="space-y-4 p-5">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide text-[#475569]">Creacion</p>
                            <p class="mt-1 text-sm text-[#0F172A]">{{ $doctor->created_at?->format('d/m/Y H:i') }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide text-[#475569]">Actualizacion</p>
                            <p class="mt-1 text-sm text-[#0F172A]">{{ $doctor->updated_at?->format('d/m/Y H:i') }}</p>
                        </div>
                    </div>
                </article>
            </aside>
        </section>
    </div>
</x-app-layout>
