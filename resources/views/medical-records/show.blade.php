@php
    $statusLabels = [
        'scheduled' => 'Programada',
        'confirmed' => 'Confirmada',
        'completed' => 'Completada',
        'cancelled' => 'Cancelada',
        'no_show' => 'No asistió',
        'active' => 'Activa',
        'cancelled_prescription' => 'Cancelada',
    ];
@endphp

<x-app-layout>
    <div class="space-y-6">
        <header class="flex flex-col gap-4 rounded-lg border border-[#E2E8F0] bg-white p-5 shadow-sm lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-[#2563EB]">Ficha médica centralizada</p>
                <h1 class="mt-2 text-2xl font-bold tracking-tight text-[#0F172A] sm:text-3xl">{{ $patient->full_name }}</h1>
                <p class="mt-2 text-sm leading-6 text-[#475569]">Historial clínico base y actividad médica reciente del paciente.</p>
            </div>
            <div class="flex flex-col gap-3 sm:flex-row">
                <a href="{{ route('medical-records.index') }}" class="inline-flex items-center justify-center rounded-lg border border-[#E2E8F0] px-4 py-3 text-sm font-semibold text-[#475569]">Volver</a>
                <a href="{{ route('patients.show', $patient) }}" class="inline-flex items-center justify-center rounded-lg border border-[#E2E8F0] px-4 py-3 text-sm font-semibold text-[#2563EB]">Ver paciente</a>
                <a href="{{ route('medical-records.edit', $medicalRecord) }}" class="inline-flex items-center justify-center rounded-lg bg-[#2563EB] px-4 py-3 text-sm font-semibold text-white shadow-sm shadow-blue-500/20">Editar historial</a>
            </div>
        </header>

        @if (session('success'))
            <div class="rounded-lg border border-[#10B981]/30 bg-[#10B981]/10 px-4 py-3 text-sm font-semibold text-[#047857]">{{ session('success') }}</div>
        @endif

        <section class="grid gap-5 lg:grid-cols-3">
            <article class="rounded-lg border border-[#E2E8F0] bg-white p-5 shadow-sm lg:col-span-2">
                <h2 class="text-base font-bold text-[#0F172A]">Datos del paciente</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div><p class="text-xs font-bold uppercase text-[#475569]">Nombre completo</p><p class="mt-1 text-sm font-semibold text-[#0F172A]">{{ $patient->full_name }}</p></div>
                    <div><p class="text-xs font-bold uppercase text-[#475569]">Identificación</p><p class="mt-1 text-sm text-[#0F172A]">{{ $patient->identification_number ?: 'Sin registrar' }}</p></div>
                    <div><p class="text-xs font-bold uppercase text-[#475569]">Nacimiento</p><p class="mt-1 text-sm text-[#0F172A]">{{ $patient->birth_date?->format('d/m/Y') ?: 'Sin registrar' }}</p></div>
                    <div><p class="text-xs font-bold uppercase text-[#475569]">Género</p><p class="mt-1 text-sm capitalize text-[#0F172A]">{{ $patient->gender ?: 'No especificado' }}</p></div>
                    <div><p class="text-xs font-bold uppercase text-[#475569]">Teléfono</p><p class="mt-1 text-sm text-[#0F172A]">{{ $patient->phone ?: 'Sin registrar' }}</p></div>
                    <div><p class="text-xs font-bold uppercase text-[#475569]">Email</p><p class="mt-1 text-sm text-[#0F172A]">{{ $patient->email ?: 'Sin registrar' }}</p></div>
                    <div><p class="text-xs font-bold uppercase text-[#475569]">Tipo de sangre</p><p class="mt-1 text-sm text-[#0F172A]">{{ $patient->blood_type ?: 'Sin registrar' }}</p></div>
                    <div class="sm:col-span-2"><p class="text-xs font-bold uppercase text-[#475569]">Alergias</p><p class="mt-1 whitespace-pre-line text-sm text-[#0F172A]">{{ $patient->allergies ?: 'Sin registrar' }}</p></div>
                </div>
            </article>

            <article class="rounded-lg border border-[#E2E8F0] bg-white p-5 shadow-sm">
                <h2 class="text-base font-bold text-[#0F172A]">Fechas</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div><dt class="font-semibold text-[#475569]">Creación</dt><dd class="mt-1 text-[#0F172A]">{{ $medicalRecord->created_at?->format('d/m/Y H:i') }}</dd></div>
                    <div><dt class="font-semibold text-[#475569]">Actualización</dt><dd class="mt-1 text-[#0F172A]">{{ $medicalRecord->updated_at?->format('d/m/Y H:i') }}</dd></div>
                </dl>
            </article>
        </section>

        <section class="grid gap-5 lg:grid-cols-2">
            @foreach ([
                'Antecedentes personales' => $medicalRecord->personal_history,
                'Antecedentes familiares' => $medicalRecord->family_history,
                'Antecedentes quirúrgicos' => $medicalRecord->surgical_history,
                'Medicamentos actuales' => $medicalRecord->current_medications,
                'Enfermedades crónicas' => $medicalRecord->chronic_diseases,
                'Observaciones generales' => $medicalRecord->observations,
            ] as $title => $content)
                <article class="rounded-lg border border-[#E2E8F0] bg-white p-5 shadow-sm">
                    <h2 class="text-base font-bold text-[#0F172A]">{{ $title }}</h2>
                    <p class="mt-3 whitespace-pre-line text-sm leading-6 text-[#475569]">{{ $content ?: 'Sin información registrada.' }}</p>
                </article>
            @endforeach
        </section>

        <section class="rounded-lg border border-[#E2E8F0] bg-white shadow-sm">
            <div class="border-b border-[#E2E8F0] px-5 py-4">
                <h2 class="text-base font-bold text-[#0F172A]">Consultas recientes</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-[#E2E8F0]">
                    <thead class="bg-[#F8FAFC]">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-[#475569]">Fecha</th>
                            <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-[#475569]">Médico</th>
                            <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-[#475569]">Diagnóstico</th>
                            <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-[#475569]">Tratamiento</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E2E8F0]">
                        @forelse ($recentConsultations as $consultation)
                            <tr>
                                <td class="px-5 py-4 text-sm text-[#0F172A]">{{ $consultation->consultation_date?->format('d/m/Y H:i') }}</td>
                                <td class="px-5 py-4 text-sm text-[#475569]">{{ $consultation->doctor?->user?->name ?: 'Sin médico' }}</td>
                                <td class="px-5 py-4 text-sm text-[#475569]">{{ $consultation->diagnosis ?: 'Sin diagnóstico' }}</td>
                                <td class="px-5 py-4 text-sm text-[#475569]">{{ str($consultation->treatment ?: 'Sin tratamiento')->limit(80) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-5 py-8 text-center text-sm text-[#475569]">No hay consultas registradas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="rounded-lg border border-[#E2E8F0] bg-white shadow-sm">
            <div class="border-b border-[#E2E8F0] px-5 py-4">
                <h2 class="text-base font-bold text-[#0F172A]">Recetas recientes</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-[#E2E8F0]">
                    <thead class="bg-[#F8FAFC]">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-[#475569]">Fecha</th>
                            <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-[#475569]">Médico</th>
                            <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-[#475569]">Estado</th>
                            <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-[#475569]">Medicamentos</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E2E8F0]">
                        @forelse ($recentPrescriptions as $prescription)
                            <tr>
                                <td class="px-5 py-4 text-sm text-[#0F172A]">{{ $prescription->prescription_date?->format('d/m/Y') }}</td>
                                <td class="px-5 py-4 text-sm text-[#475569]">{{ $prescription->doctor?->user?->name ?: 'Sin médico' }}</td>
                                <td class="px-5 py-4 text-sm">
                                    <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-bold {{ $prescription->status === 'active' ? 'border-[#10B981]/20 bg-[#10B981]/10 text-[#10B981]' : 'border-[#EF4444]/20 bg-[#EF4444]/10 text-[#EF4444]' }}">
                                        {{ $prescription->status === 'active' ? 'Activa' : 'Cancelada' }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-sm text-[#475569]">{{ $prescription->items->pluck('medication_name')->take(3)->join(', ') ?: 'Sin medicamentos' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-5 py-8 text-center text-sm text-[#475569]">No hay recetas registradas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="rounded-lg border border-[#E2E8F0] bg-white shadow-sm">
            <div class="border-b border-[#E2E8F0] px-5 py-4">
                <h2 class="text-base font-bold text-[#0F172A]">Citas recientes</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-[#E2E8F0]">
                    <thead class="bg-[#F8FAFC]">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-[#475569]">Fecha</th>
                            <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-[#475569]">Hora</th>
                            <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-[#475569]">Médico</th>
                            <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-[#475569]">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E2E8F0]">
                        @forelse ($recentAppointments as $appointment)
                            <tr>
                                <td class="px-5 py-4 text-sm text-[#0F172A]">{{ $appointment->appointment_date?->format('d/m/Y') }}</td>
                                <td class="px-5 py-4 text-sm text-[#475569]">{{ substr((string) $appointment->start_time, 0, 5) }}</td>
                                <td class="px-5 py-4 text-sm text-[#475569]">{{ $appointment->doctor?->user?->name ?: 'Sin médico' }}</td>
                                <td class="px-5 py-4 text-sm text-[#475569]">{{ $statusLabels[$appointment->status] ?? $appointment->status }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-5 py-8 text-center text-sm text-[#475569]">No hay citas registradas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-app-layout>
