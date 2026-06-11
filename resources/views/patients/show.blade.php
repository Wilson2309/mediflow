<x-app-layout>
    <div class="space-y-6">
        <section class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-[#2563EB]">Ficha del paciente</p>
                <h1 class="mt-2 text-2xl font-bold tracking-tight text-[#0F172A] sm:text-3xl">{{ $patient->full_name }}</h1>
                <p class="mt-2 text-sm text-[#475569]">Informacion general registrada en MediFlow.</p>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row">
                <a href="{{ route('patients.index') }}" class="inline-flex items-center justify-center rounded-lg border border-[#E2E8F0] px-4 py-3 text-sm font-semibold text-[#475569] transition hover:border-[#2563EB] hover:text-[#2563EB]">
                    Volver
                </a>
                @can('patients.update')<a href="{{ route('patients.edit', $patient) }}" class="inline-flex items-center justify-center rounded-lg bg-[#2563EB] px-4 py-3 text-sm font-semibold text-white shadow-sm shadow-blue-500/20 transition hover:bg-blue-700">Editar paciente</a>@endcan
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
                        <h2 class="text-base font-bold text-[#0F172A]">Datos personales</h2>
                    </div>
                    <div class="grid gap-5 p-5 sm:grid-cols-2">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide text-[#475569]">Nombres</p>
                            <p class="mt-1 text-sm font-semibold text-[#0F172A]">{{ $patient->first_name }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide text-[#475569]">Apellidos</p>
                            <p class="mt-1 text-sm font-semibold text-[#0F172A]">{{ $patient->last_name }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide text-[#475569]">Identificacion</p>
                            <p class="mt-1 text-sm text-[#0F172A]">{{ $patient->identification_number ?: 'Sin registrar' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide text-[#475569]">Fecha de nacimiento</p>
                            <p class="mt-1 text-sm text-[#0F172A]">{{ $patient->birth_date?->format('d/m/Y') ?: 'Sin registrar' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide text-[#475569]">Genero</p>
                            <p class="mt-1 text-sm capitalize text-[#0F172A]">{{ $patient->gender ?: 'No especificado' }}</p>
                        </div>
                    </div>
                </article>

                <article class="rounded-lg border border-[#E2E8F0] bg-white shadow-sm">
                    <div class="border-b border-[#E2E8F0] px-5 py-4">
                        <h2 class="text-base font-bold text-[#0F172A]">Informacion de contacto</h2>
                    </div>
                    <div class="grid gap-5 p-5 sm:grid-cols-2">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide text-[#475569]">Telefono</p>
                            <p class="mt-1 text-sm text-[#0F172A]">{{ $patient->phone ?: 'Sin registrar' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide text-[#475569]">Email</p>
                            <p class="mt-1 text-sm text-[#0F172A]">{{ $patient->email ?: 'Sin registrar' }}</p>
                        </div>
                        <div class="sm:col-span-2">
                            <p class="text-xs font-bold uppercase tracking-wide text-[#475569]">Direccion</p>
                            <p class="mt-1 text-sm text-[#0F172A]">{{ $patient->address ?: 'Sin registrar' }}</p>
                        </div>
                    </div>
                </article>

                <article class="rounded-lg border border-[#E2E8F0] bg-white shadow-sm">
                    <div class="border-b border-[#E2E8F0] px-5 py-4">
                        <h2 class="text-base font-bold text-[#0F172A]">Informacion medica basica</h2>
                    </div>
                    <div class="grid gap-5 p-5 sm:grid-cols-2">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide text-[#475569]">Tipo de sangre</p>
                            <p class="mt-1 text-sm text-[#0F172A]">{{ $patient->blood_type ?: 'Sin registrar' }}</p>
                        </div>
                        <div class="sm:col-span-2">
                            <p class="text-xs font-bold uppercase tracking-wide text-[#475569]">Alergias</p>
                            <p class="mt-1 whitespace-pre-line text-sm text-[#0F172A]">{{ $patient->allergies ?: 'Sin registrar' }}</p>
                        </div>
                    </div>
                </article>

                <article class="rounded-lg border border-dashed border-[#38BDF8]/50 bg-[#38BDF8]/5 p-5">
                    <h2 class="text-base font-bold text-[#0F172A]">Historial clinico</h2>
                    <p class="mt-2 text-sm leading-6 text-[#475569]">
                        {{ $patient->medicalRecord ? 'Ficha clinica centralizada disponible para este paciente.' : 'Este paciente todavia no tiene historial clinico base.' }}
                    </p>
                    <div class="mt-4">
                        @if ($patient->medicalRecord)
                            <a href="{{ route('medical-records.show', $patient->medicalRecord) }}" class="inline-flex items-center justify-center rounded-lg bg-[#2563EB] px-4 py-2.5 text-sm font-semibold text-white shadow-sm shadow-blue-500/20">
                                Ver historial clinico
                            </a>
                        @elseif (auth()->user()->can('medical_records.create'))
                            <a href="{{ route('medical-records.create', ['patient_id' => $patient->id]) }}" class="inline-flex items-center justify-center rounded-lg bg-[#2563EB] px-4 py-2.5 text-sm font-semibold text-white shadow-sm shadow-blue-500/20">
                                Crear historial clinico
                            </a>
                        @endif
                    </div>
                </article>
            </div>

            <aside class="space-y-6">
                <article class="rounded-lg border border-[#E2E8F0] bg-white shadow-sm">
                    <div class="border-b border-[#E2E8F0] px-5 py-4">
                        <h2 class="text-base font-bold text-[#0F172A]">Contacto de emergencia</h2>
                    </div>
                    <div class="space-y-4 p-5">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide text-[#475569]">Nombre</p>
                            <p class="mt-1 text-sm text-[#0F172A]">{{ $patient->emergency_contact_name ?: 'Sin registrar' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide text-[#475569]">Telefono</p>
                            <p class="mt-1 text-sm text-[#0F172A]">{{ $patient->emergency_contact_phone ?: 'Sin registrar' }}</p>
                        </div>
                    </div>
                </article>

                <article class="rounded-lg border border-[#E2E8F0] bg-white shadow-sm">
                    <div class="border-b border-[#E2E8F0] px-5 py-4">
                        <h2 class="text-base font-bold text-[#0F172A]">Estado del paciente</h2>
                    </div>
                    <div class="p-5">
                        @if ($patient->status === 'active')
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
                            <p class="mt-1 text-sm text-[#0F172A]">{{ $patient->created_at?->format('d/m/Y H:i') }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide text-[#475569]">Actualizacion</p>
                            <p class="mt-1 text-sm text-[#0F172A]">{{ $patient->updated_at?->format('d/m/Y H:i') }}</p>
                        </div>
                    </div>
                </article>
            </aside>
        </section>
    </div>
</x-app-layout>
