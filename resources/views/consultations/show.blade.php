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
                <a href="{{ route('consultations.edit', $consultation) }}" class="inline-flex items-center justify-center rounded-lg bg-[#2563EB] px-4 py-3 text-sm font-semibold text-white shadow-sm shadow-blue-500/20">Editar consulta</a>
            </div>
        </header>

        @if (session('success'))
            <div class="rounded-lg border border-[#10B981]/30 bg-[#10B981]/10 px-4 py-3 text-sm font-semibold text-[#047857]">{{ session('success') }}</div>
        @endif

        <section class="grid gap-5 lg:grid-cols-3">
            <article class="rounded-lg border border-[#E2E8F0] bg-white p-5 shadow-sm">
                <h2 class="text-base font-bold text-[#0F172A]">Información general</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div><dt class="font-semibold text-[#475569]">Fecha</dt><dd class="mt-1 text-[#0F172A]">{{ $consultation->consultation_date?->format('d/m/Y H:i') }}</dd></div>
                    <div><dt class="font-semibold text-[#475569]">Creación</dt><dd class="mt-1 text-[#0F172A]">{{ $consultation->created_at?->format('d/m/Y H:i') }}</dd></div>
                    <div><dt class="font-semibold text-[#475569]">Actualización</dt><dd class="mt-1 text-[#0F172A]">{{ $consultation->updated_at?->format('d/m/Y H:i') }}</dd></div>
                </dl>
            </article>
            <article class="rounded-lg border border-[#E2E8F0] bg-white p-5 shadow-sm">
                <h2 class="text-base font-bold text-[#0F172A]">Paciente</h2>
                <p class="mt-4 text-sm font-semibold text-[#0F172A]">{{ $consultation->patient?->full_name }}</p>
                <p class="mt-1 text-sm text-[#475569]">{{ $consultation->patient?->identification_number ?: 'Sin identificación' }}</p>
                <p class="mt-1 text-sm text-[#475569]">{{ $consultation->patient?->phone ?: 'Sin teléfono' }}</p>
            </article>
            <article class="rounded-lg border border-[#E2E8F0] bg-white p-5 shadow-sm">
                <h2 class="text-base font-bold text-[#0F172A]">Médico</h2>
                <p class="mt-4 text-sm font-semibold text-[#0F172A]">{{ $consultation->doctor?->user?->name }}</p>
                <p class="mt-1 text-sm text-[#475569]">{{ $consultation->doctor?->specialty?->name ?: 'Sin especialidad' }}</p>
                <p class="mt-1 text-sm text-[#475569]">{{ $consultation->doctor?->phone ?: 'Sin teléfono' }}</p>
            </article>
        </section>

        <section class="rounded-lg border border-[#E2E8F0] bg-white p-5 shadow-sm">
            <h2 class="text-base font-bold text-[#0F172A]">Cita asociada</h2>
            @if ($consultation->appointment)
                <p class="mt-3 text-sm text-[#475569]">{{ $consultation->appointment->appointment_date?->format('d/m/Y') }} · {{ substr((string) $consultation->appointment->start_time, 0, 5) }} · {{ $consultation->appointment->service?->name ?: 'Sin servicio' }}</p>
            @else
                <p class="mt-3 text-sm text-[#475569]">Consulta registrada sin cita asociada.</p>
            @endif
        </section>

        <section class="grid gap-5 lg:grid-cols-2">
            @foreach ([
                'Motivo de consulta' => $consultation->reason,
                'Síntomas' => $consultation->symptoms,
                'Diagnóstico' => $consultation->diagnosis,
                'Tratamiento' => $consultation->treatment,
                'Observaciones' => $consultation->observations,
            ] as $title => $content)
                <article class="rounded-lg border border-[#E2E8F0] bg-white p-5 shadow-sm">
                    <h2 class="text-base font-bold text-[#0F172A]">{{ $title }}</h2>
                    <p class="mt-3 whitespace-pre-line text-sm leading-6 text-[#475569]">{{ $content ?: 'Sin información registrada.' }}</p>
                </article>
            @endforeach
        </section>

        <section class="rounded-lg border border-[#E2E8F0] bg-white p-5 shadow-sm">
            <h2 class="text-base font-bold text-[#0F172A]">Signos vitales</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                <div class="rounded-lg bg-[#F8FAFC] p-4"><p class="text-xs font-bold uppercase text-[#475569]">Peso</p><p class="mt-2 text-sm font-semibold text-[#0F172A]">{{ $consultation->weight ? $consultation->weight.' kg' : 'No registrado' }}</p></div>
                <div class="rounded-lg bg-[#F8FAFC] p-4"><p class="text-xs font-bold uppercase text-[#475569]">Altura</p><p class="mt-2 text-sm font-semibold text-[#0F172A]">{{ $consultation->height ? $consultation->height.' m' : 'No registrada' }}</p></div>
                <div class="rounded-lg bg-[#F8FAFC] p-4"><p class="text-xs font-bold uppercase text-[#475569]">Temperatura</p><p class="mt-2 text-sm font-semibold text-[#0F172A]">{{ $consultation->temperature ? $consultation->temperature.' °C' : 'No registrada' }}</p></div>
                <div class="rounded-lg bg-[#F8FAFC] p-4"><p class="text-xs font-bold uppercase text-[#475569]">Presión arterial</p><p class="mt-2 text-sm font-semibold text-[#0F172A]">{{ $consultation->blood_pressure ?: 'No registrada' }}</p></div>
                <div class="rounded-lg bg-[#F8FAFC] p-4"><p class="text-xs font-bold uppercase text-[#475569]">Frecuencia cardíaca</p><p class="mt-2 text-sm font-semibold text-[#0F172A]">{{ $consultation->heart_rate ? $consultation->heart_rate.' lpm' : 'No registrada' }}</p></div>
            </div>
        </section>

        <section class="rounded-lg border border-dashed border-[#38BDF8]/50 bg-[#38BDF8]/5 p-5">
            <h2 class="text-base font-bold text-[#0F172A]">Recetas</h2>
            <p class="mt-2 text-sm text-[#475569]">Recetas disponibles en una próxima fase.</p>
        </section>
    </div>
</x-app-layout>
