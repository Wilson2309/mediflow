<x-app-layout>
    <div class="space-y-6">
        <header class="flex flex-col gap-4 rounded-lg border border-[#E2E8F0] bg-white p-5 shadow-sm lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-[#2563EB]">Ficha de consulta</p>
                <h1 class="mt-2 text-2xl font-bold tracking-tight text-[#0F172A] sm:text-3xl">{{ $consultation->patient?->full_name }}</h1>
                <p class="mt-2 text-sm text-[#475569]">{{ $consultation->consultation_date?->format('d/m/Y H:i') }}</p>
            </div>
            <div class="flex flex-col gap-3 sm:flex-row">
                <a href="{{ route('consultations.index') }}" class="inline-flex items-center justify-center rounded-lg border border-[#E2E8F0] px-4 py-3 text-sm font-semibold text-[#475569]">Volver</a>
                @can('consultations.update')<a href="{{ route('consultations.edit', $consultation) }}" class="inline-flex items-center justify-center rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-4 py-3 text-sm font-semibold text-[#0F172A]">Editar consulta</a>@endcan
                @can('prescriptions.create')
                    <a href="{{ route('prescriptions.create', ['consultation_id' => $consultation->id]) }}" class="inline-flex items-center justify-center rounded-lg bg-[#2563EB] px-4 py-3 text-sm font-semibold text-white shadow-sm shadow-blue-500/20 hover:bg-blue-700">
                        Generar Receta
                    </a>
                @endcan
            </div>
        </header>

        @if (session('success'))
            <div class="rounded-lg border border-[#10B981]/30 bg-[#10B981]/10 px-4 py-3 text-sm font-semibold text-[#047857]">{{ session('success') }}</div>
        @endif

        <div class="grid gap-6 lg:grid-cols-12">
            <!-- Columna Izquierda: Historial Clínico Base (Span 4) -->
            <div class="lg:col-span-4 space-y-6">
                <section class="rounded-lg border border-[#E2E8F0] bg-white p-5 shadow-sm">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-bold text-[#0F172A]">Historial Clínico Base</h2>
                        <a href="{{ route('medical-records.show', $medicalRecord ?? $consultation->patient->id) }}" class="text-xs font-semibold text-[#2563EB] hover:underline" target="_blank">Ver ficha completa</a>
                    </div>
                    
                    @if($medicalRecord)
                        <div class="space-y-4">
                            <div><h3 class="text-xs font-bold uppercase text-[#475569]">Antecedentes personales</h3><p class="mt-1 text-sm text-[#0F172A]">{{ $medicalRecord->personal_history ?: 'Ninguno registrado' }}</p></div>
                            <div class="border-t border-[#E2E8F0] pt-4"><h3 class="text-xs font-bold uppercase text-[#475569]">Antecedentes familiares</h3><p class="mt-1 text-sm text-[#0F172A]">{{ $medicalRecord->family_history ?: 'Ninguno registrado' }}</p></div>
                            <div class="border-t border-[#E2E8F0] pt-4"><h3 class="text-xs font-bold uppercase text-[#475569]">Alergias</h3><p class="mt-1 text-sm font-semibold text-[#EF4444]">{{ $medicalRecord->allergies ?: 'Ninguna registrada' }}</p></div>
                            <div class="border-t border-[#E2E8F0] pt-4"><h3 class="text-xs font-bold uppercase text-[#475569]">Hábitos</h3><p class="mt-1 text-sm text-[#0F172A]">{{ $medicalRecord->habits ?: 'Ninguno registrado' }}</p></div>
                            <div class="border-t border-[#E2E8F0] pt-4"><h3 class="text-xs font-bold uppercase text-[#475569]">Enfermedades Crónicas</h3><p class="mt-1 text-sm text-[#0F172A]">{{ $medicalRecord->chronic_diseases ?: 'Ninguna registrada' }}</p></div>
                            <div class="border-t border-[#E2E8F0] pt-4"><h3 class="text-xs font-bold uppercase text-[#475569]">Medicación Actual</h3><p class="mt-1 text-sm text-[#0F172A]">{{ $medicalRecord->current_medications ?: 'Ninguna registrada' }}</p></div>
                        </div>
                    @else
                        <div class="rounded-lg bg-[#F8FAFC] p-4 text-center">
                            <p class="text-sm text-[#475569]">El paciente no tiene un historial clínico base creado.</p>
                            @can('medical_records.create')
                                <a href="{{ route('medical-records.create', ['patient_id' => $consultation->patient_id]) }}" class="mt-3 inline-block rounded border border-[#2563EB] px-3 py-1 text-xs font-semibold text-[#2563EB] hover:bg-[#2563EB] hover:text-white">Crear historial base</a>
                            @endcan
                        </div>
                    @endif
                </section>
                
                <section class="rounded-lg border border-[#E2E8F0] bg-white p-5 shadow-sm">
                    <h2 class="text-base font-bold text-[#0F172A]">Médico y Cita</h2>
                    <p class="mt-4 text-sm font-semibold text-[#0F172A]">{{ $consultation->doctor?->user?->name }} ({{ $consultation->doctor?->specialty?->name }})</p>
                    @if ($consultation->appointment)
                        <p class="mt-3 text-sm text-[#475569]">Cita: {{ $consultation->appointment->appointment_date?->format('d/m/Y') }} · {{ substr((string) $consultation->appointment->start_time, 0, 5) }}</p>
                        <p class="mt-1 text-sm text-[#475569]">Servicio: {{ $consultation->appointment->service?->name ?: 'Sin servicio' }}</p>
                    @else
                        <p class="mt-3 text-sm text-[#475569]">Consulta registrada sin cita asociada.</p>
                    @endif
                </section>
            </div>

            <!-- Columna Derecha: Evolución de la Consulta (Span 8) -->
            <div class="lg:col-span-8 space-y-6">
                
                <section class="rounded-lg border border-[#E2E8F0] bg-white p-5 shadow-sm">
                    <h2 class="text-lg font-bold text-[#0F172A] mb-4">Signos Vitales</h2>
                    <div class="grid gap-4 sm:grid-cols-3 lg:grid-cols-5">
                        <div class="rounded-lg bg-[#F8FAFC] p-4 border border-[#E2E8F0]"><p class="text-xs font-bold uppercase text-[#475569]">Peso</p><p class="mt-2 text-sm font-semibold text-[#0F172A]">{{ $consultation->weight ? $consultation->weight.' kg' : '--' }}</p></div>
                        <div class="rounded-lg bg-[#F8FAFC] p-4 border border-[#E2E8F0]"><p class="text-xs font-bold uppercase text-[#475569]">Altura</p><p class="mt-2 text-sm font-semibold text-[#0F172A]">{{ $consultation->height ? $consultation->height.' m' : '--' }}</p></div>
                        <div class="rounded-lg bg-[#F8FAFC] p-4 border border-[#E2E8F0]"><p class="text-xs font-bold uppercase text-[#475569]">Temp.</p><p class="mt-2 text-sm font-semibold text-[#0F172A]">{{ $consultation->temperature ? $consultation->temperature.' °C' : '--' }}</p></div>
                        <div class="rounded-lg bg-[#F8FAFC] p-4 border border-[#E2E8F0]"><p class="text-xs font-bold uppercase text-[#475569]">Presión</p><p class="mt-2 text-sm font-semibold text-[#0F172A]">{{ $consultation->blood_pressure ?: '--' }}</p></div>
                        <div class="rounded-lg bg-[#F8FAFC] p-4 border border-[#E2E8F0]"><p class="text-xs font-bold uppercase text-[#475569]">F.C.</p><p class="mt-2 text-sm font-semibold text-[#0F172A]">{{ $consultation->heart_rate ? $consultation->heart_rate.' lpm' : '--' }}</p></div>
                    </div>
                </section>

                <section class="grid gap-5 lg:grid-cols-2">
                    <article class="rounded-lg border border-[#E2E8F0] bg-white p-5 shadow-sm">
                        <h2 class="text-sm font-bold text-[#2563EB] uppercase tracking-wide">Motivo de consulta (S)</h2>
                        <p class="mt-3 whitespace-pre-line text-sm leading-6 text-[#0F172A]">{{ $consultation->reason ?: 'Sin información registrada.' }}</p>
                    </article>
                    <article class="rounded-lg border border-[#E2E8F0] bg-white p-5 shadow-sm">
                        <h2 class="text-sm font-bold text-[#2563EB] uppercase tracking-wide">Síntomas / Examen Físico (O)</h2>
                        <p class="mt-3 whitespace-pre-line text-sm leading-6 text-[#0F172A]">{{ $consultation->symptoms ?: 'Sin información registrada.' }}</p>
                    </article>
                    <article class="rounded-lg border border-[#E2E8F0] bg-white p-5 shadow-sm">
                        <h2 class="text-sm font-bold text-[#2563EB] uppercase tracking-wide">Diagnóstico (A)</h2>
                        <p class="mt-3 whitespace-pre-line text-sm leading-6 text-[#0F172A]">{{ $consultation->diagnosis ?: 'Sin información registrada.' }}</p>
                    </article>
                    <article class="rounded-lg border border-[#E2E8F0] bg-white p-5 shadow-sm">
                        <h2 class="text-sm font-bold text-[#2563EB] uppercase tracking-wide">Tratamiento y Plan (P)</h2>
                        <p class="mt-3 whitespace-pre-line text-sm leading-6 text-[#0F172A]">{{ $consultation->treatment ?: 'Sin información registrada.' }}</p>
                    </article>
                    <article class="rounded-lg border border-[#E2E8F0] bg-white p-5 shadow-sm lg:col-span-2">
                        <h2 class="text-sm font-bold text-[#0F172A]">Observaciones</h2>
                        <p class="mt-3 whitespace-pre-line text-sm leading-6 text-[#475569]">{{ $consultation->observations ?: 'Sin información registrada.' }}</p>
                    </article>
                </section>

                @if($consultation->prescriptions && $consultation->prescriptions->count() > 0)
                    <section class="rounded-lg border border-[#10B981] bg-white shadow-sm overflow-hidden">
                        <div class="border-b border-[#E2E8F0] bg-[#F8FAFC] px-5 py-4 flex justify-between items-center">
                            <h2 class="text-base font-bold text-[#0F172A]">Recetas Generadas</h2>
                            <span class="inline-flex rounded-full bg-[#10B981]/10 px-2.5 py-1 text-xs font-bold text-[#10B981]">{{ $consultation->prescriptions->count() }} receta(s)</span>
                        </div>
                        <div class="divide-y divide-[#E2E8F0]">
                            @foreach($consultation->prescriptions as $prescription)
                                <div class="px-5 py-4 flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-bold text-[#0F172A]">Receta #{{ $prescription->id }}</p>
                                        <p class="text-xs text-[#475569] mt-1">{{ $prescription->prescription_date?->format('d/m/Y') }} · {{ $prescription->items ? $prescription->items->count() : 0 }} medicamentos</p>
                                    </div>
                                    <a href="{{ route('prescriptions.show', $prescription) }}" class="text-sm font-semibold text-[#2563EB] hover:underline">Ver Receta</a>
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endif
                
            </div>
        </div>
    </div>
</x-app-layout>
