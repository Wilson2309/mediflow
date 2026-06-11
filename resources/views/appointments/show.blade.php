<x-app-layout>
    @php
        $statusLabels = ['scheduled' => 'Programada', 'confirmed' => 'Confirmada', 'completed' => 'Completada', 'cancelled' => 'Cancelada', 'no_show' => 'No asistió'];
    @endphp
    <div class="space-y-6">
        <section class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-[#2563EB]">Ficha de cita</p>
                <h1 class="mt-2 text-2xl font-bold tracking-tight text-[#0F172A] sm:text-3xl">{{ $appointment->patient?->full_name }}</h1>
                <p class="mt-2 text-sm text-[#475569]">{{ $appointment->appointment_date?->format('d/m/Y') }} {{ substr((string) $appointment->start_time, 0, 5) }}</p>
            </div>
            <div class="flex flex-col gap-3 sm:flex-row">
                <a href="{{ route('appointments.index') }}" class="inline-flex items-center justify-center rounded-lg border border-[#E2E8F0] px-4 py-3 text-sm font-semibold text-[#475569]">Volver</a>
                @can('appointments.update')<a href="{{ route('appointments.edit', $appointment) }}" class="inline-flex items-center justify-center rounded-lg bg-[#2563EB] px-4 py-3 text-sm font-semibold text-white">Editar cita</a>@endcan
            </div>
        </section>
        @if (session('success'))<div class="rounded-lg border border-[#10B981]/20 bg-[#10B981]/10 px-4 py-3 text-sm font-semibold text-[#047857]">{{ session('success') }}</div>@endif
        <section class="grid gap-6 xl:grid-cols-3">
            <div class="space-y-6 xl:col-span-2">
                <article class="rounded-lg border border-[#E2E8F0] bg-white shadow-sm"><div class="border-b border-[#E2E8F0] px-5 py-4"><h2 class="text-base font-bold text-[#0F172A]">Datos de la cita</h2></div><div class="grid gap-5 p-5 sm:grid-cols-2"><div><p class="text-xs font-bold uppercase text-[#475569]">Fecha</p><p class="mt-1 text-sm text-[#0F172A]">{{ $appointment->appointment_date?->format('d/m/Y') }}</p></div><div><p class="text-xs font-bold uppercase text-[#475569]">Hora</p><p class="mt-1 text-sm text-[#0F172A]">{{ substr((string) $appointment->start_time, 0, 5) }}{{ $appointment->end_time ? ' - '.substr((string) $appointment->end_time, 0, 5) : '' }}</p></div><div><p class="text-xs font-bold uppercase text-[#475569]">Estado</p><p class="mt-1 text-sm text-[#0F172A]">{{ $statusLabels[$appointment->status] ?? $appointment->status }}</p></div></div></article>
                <article class="rounded-lg border border-[#E2E8F0] bg-white shadow-sm"><div class="border-b border-[#E2E8F0] px-5 py-4"><h2 class="text-base font-bold text-[#0F172A]">Paciente</h2></div><div class="p-5 text-sm text-[#0F172A]">{{ $appointment->patient?->full_name }} - {{ $appointment->patient?->identification_number ?: 'Sin identificación' }}</div></article>
                <article class="rounded-lg border border-[#E2E8F0] bg-white shadow-sm"><div class="border-b border-[#E2E8F0] px-5 py-4"><h2 class="text-base font-bold text-[#0F172A]">Médico</h2></div><div class="p-5 text-sm text-[#0F172A]">{{ $appointment->doctor?->user?->name }}{{ $appointment->doctor?->specialty ? ' - '.$appointment->doctor->specialty->name : '' }}</div></article>
                <article class="rounded-lg border border-[#E2E8F0] bg-white shadow-sm"><div class="border-b border-[#E2E8F0] px-5 py-4"><h2 class="text-base font-bold text-[#0F172A]">Motivo</h2></div><div class="p-5 whitespace-pre-line text-sm text-[#0F172A]">{{ $appointment->reason ?: 'Sin motivo registrado' }}</div></article>
                <article class="rounded-lg border border-dashed border-[#38BDF8]/50 bg-[#38BDF8]/5 p-5"><h2 class="text-base font-bold text-[#0F172A]">Consulta médica</h2><p class="mt-2 text-sm text-[#475569]">Consulta médica disponible en una próxima fase.</p></article>
            </div>
            <aside class="space-y-6">
                <article class="rounded-lg border border-[#E2E8F0] bg-white shadow-sm"><div class="border-b border-[#E2E8F0] px-5 py-4"><h2 class="text-base font-bold text-[#0F172A]">Servicio</h2></div><div class="p-5 text-sm text-[#0F172A]">{{ $appointment->service?->name ?? 'Sin servicio' }}</div></article>
                <article class="rounded-lg border border-[#E2E8F0] bg-white shadow-sm"><div class="border-b border-[#E2E8F0] px-5 py-4"><h2 class="text-base font-bold text-[#0F172A]">Notas</h2></div><div class="p-5 whitespace-pre-line text-sm text-[#0F172A]">{{ $appointment->notes ?: 'Sin notas' }}</div></article>
                <article class="rounded-lg border border-[#E2E8F0] bg-white shadow-sm"><div class="border-b border-[#E2E8F0] px-5 py-4"><h2 class="text-base font-bold text-[#0F172A]">Fechas</h2></div><div class="space-y-4 p-5 text-sm text-[#0F172A]"><p>Creacion: {{ $appointment->created_at?->format('d/m/Y H:i') }}</p><p>Actualizacion: {{ $appointment->updated_at?->format('d/m/Y H:i') }}</p></div></article>
            </aside>
        </section>
    </div>
</x-app-layout>
