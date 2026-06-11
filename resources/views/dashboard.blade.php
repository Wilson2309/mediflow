@php
    $patientCount = $patientCount ?? 0;
    $activeDoctorCount = $activeDoctorCount ?? 0;
    $todayAppointmentCount = $todayAppointmentCount ?? 0;
    $activePrescriptionCount = $activePrescriptionCount ?? 0;
    $monthlyPaidIncome = $monthlyPaidIncome ?? 0;
    $pendingPaymentsCount = $pendingPaymentsCount ?? 0;
    $upcomingAppointments = $upcomingAppointments ?? collect();

    $icons = [
        'calendar' => '<svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 3.75v3M16.5 3.75v3M4.5 9.75h15M6 6h12a1.5 1.5 0 0 1 1.5 1.5V18A2.25 2.25 0 0 1 17.25 20.25H6.75A2.25 2.25 0 0 1 4.5 18V7.5A1.5 1.5 0 0 1 6 6Z" /></svg>',
        'patients' => '<svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 7.5a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 20.25a7.5 7.5 0 0 1 15 0" /></svg>',
        'consultations' => '<svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5h7.5M6.75 5.75h10.5A2.25 2.25 0 0 1 19.5 8v10.25a2.25 2.25 0 0 1-2.25 2.25H6.75a2.25 2.25 0 0 1-2.25-2.25V8a2.25 2.25 0 0 1 2.25-2.25ZM8.25 11.25h7.5M8.25 15h4.5" /></svg>',
        'income' => '<svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 7.5A2.25 2.25 0 0 1 6 5.25h12A2.25 2.25 0 0 1 20.25 7.5v9A2.25 2.25 0 0 1 18 18.75H6A2.25 2.25 0 0 1 3.75 16.5v-9ZM3.75 9.75h16.5M7.5 15h3" /></svg>',
        'pending' => '<svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l3.5 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>',
        'doctors' => '<svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75a3.75 3.75 0 1 0 0 7.5 3.75 3.75 0 0 0 0-7.5ZM4.5 21a7.5 7.5 0 0 1 15 0M18 4.5v4.5M20.25 6.75h-4.5" /></svg>',
        'plus' => '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14" /></svg>',
    ];

    $stats = [
        ['label' => 'Citas de hoy', 'value' => number_format($todayAppointmentCount), 'summary' => 'Programadas y confirmadas', 'icon' => 'calendar', 'iconClass' => 'bg-[#2563EB] text-white', 'trend' => 'Real'],
        ['label' => 'Pacientes registrados', 'value' => number_format($patientCount), 'summary' => 'Pacientes de tu clinica', 'icon' => 'patients', 'iconClass' => 'bg-[#38BDF8]/15 text-[#2563EB]', 'trend' => 'Real'],
        ['label' => 'Consultas realizadas', 'value' => $consultationCount ?? 0, 'summary' => 'Atenciones registradas', 'icon' => 'consultations', 'iconClass' => 'bg-[#10B981]/15 text-[#10B981]', 'trend' => 'Real'],
        ['label' => 'Ingresos del mes', 'value' => '$'.number_format((float) $monthlyPaidIncome, 2), 'summary' => 'Pagos marcados como pagados', 'icon' => 'income', 'iconClass' => 'bg-[#0F172A] text-white', 'trend' => 'Real'],
        ['label' => 'Pagos pendientes', 'value' => number_format($pendingPaymentsCount), 'summary' => 'Cobros por confirmar', 'icon' => 'pending', 'iconClass' => 'bg-[#F59E0B]/15 text-[#F59E0B]', 'trend' => 'Real'],
        ['label' => 'Medicos activos', 'value' => number_format($activeDoctorCount), 'summary' => 'Profesionales activos', 'icon' => 'doctors', 'iconClass' => 'bg-[#EF4444]/10 text-[#EF4444]', 'trend' => 'Real'],
    ];

    $statusLabels = [
        'scheduled' => 'Programada',
        'confirmed' => 'Confirmada',
        'completed' => 'Completada',
        'cancelled' => 'Cancelada',
        'no_show' => 'No asistio',
    ];
    $statusClasses = [
        'scheduled' => 'border-[#2563EB]/20 bg-[#2563EB]/10 text-[#2563EB]',
        'confirmed' => 'border-[#10B981]/20 bg-[#10B981]/10 text-[#10B981]',
        'completed' => 'border-slate-200 bg-slate-100 text-[#475569]',
        'cancelled' => 'border-[#EF4444]/20 bg-[#EF4444]/10 text-[#EF4444]',
        'no_show' => 'border-[#F59E0B]/20 bg-[#F59E0B]/10 text-[#F59E0B]',
    ];
@endphp

<x-app-layout>
    <div class="space-y-7">
        <section class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-[#2563EB]">Panel principal</p>
                <h1 class="mt-2 text-2xl font-bold tracking-tight text-[#0F172A] sm:text-3xl">Dashboard de MediFlow</h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-[#475569]">Vista operativa para seguimiento clínico, agenda médica y control financiero del consultorio.</p>
            </div>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <div class="rounded-lg border border-[#E2E8F0] bg-white px-4 py-3 text-sm font-semibold text-[#475569] shadow-sm">{{ now()->format('d/m/Y') }}</div>
                <a href="{{ route('appointments.create') }}" class="inline-flex items-center justify-center gap-2 rounded-lg bg-[#2563EB] px-4 py-3 text-sm font-semibold text-white shadow-sm shadow-blue-500/20 transition hover:bg-blue-700">{!! $icons['plus'] !!} Nueva cita</a>
            </div>
        </section>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($stats as $stat)
                <article class="rounded-lg border border-[#E2E8F0] bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                    <div class="flex items-start justify-between gap-4">
                        <div><p class="text-sm font-semibold text-[#475569]">{{ $stat['label'] }}</p><p class="mt-3 text-3xl font-bold tracking-tight text-[#0F172A]">{{ $stat['value'] }}</p></div>
                        <span class="grid h-12 w-12 place-items-center rounded-lg {{ $stat['iconClass'] }}">{!! $icons[$stat['icon']] !!}</span>
                    </div>
                    <div class="mt-5 flex items-center justify-between gap-3 border-t border-[#E2E8F0] pt-4">
                        <p class="truncate text-sm text-[#475569]">{{ $stat['summary'] }}</p>
                        <span class="shrink-0 rounded-full bg-[#F8FAFC] px-2.5 py-1 text-xs font-bold text-[#0F172A]">{{ $stat['trend'] }}</span>
                    </div>
                </article>
            @endforeach
        </section>

        <section class="grid gap-6 xl:grid-cols-3">
            <div class="rounded-lg border border-[#E2E8F0] bg-white shadow-sm xl:col-span-2">
                <div class="flex flex-col gap-3 border-b border-[#E2E8F0] px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div><h2 class="text-base font-bold text-[#0F172A]">Próximas citas</h2><p class="mt-1 text-sm text-[#475569]">Agenda clínica priorizada para los próximos días.</p></div>
                    <a href="{{ route('appointments.index') }}" class="inline-flex items-center justify-center rounded-lg border border-[#E2E8F0] px-3 py-2 text-sm font-semibold text-[#2563EB] transition hover:border-[#2563EB] hover:bg-[#2563EB]/5">Ver agenda</a>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-[#E2E8F0] text-left">
                        <thead class="bg-[#F8FAFC]"><tr><th class="px-5 py-3 text-xs font-bold uppercase tracking-wide text-[#475569]">Fecha</th><th class="px-5 py-3 text-xs font-bold uppercase tracking-wide text-[#475569]">Hora</th><th class="px-5 py-3 text-xs font-bold uppercase tracking-wide text-[#475569]">Paciente</th><th class="hidden px-5 py-3 text-xs font-bold uppercase tracking-wide text-[#475569] md:table-cell">Medico</th><th class="hidden px-5 py-3 text-xs font-bold uppercase tracking-wide text-[#475569] lg:table-cell">Servicio</th><th class="px-5 py-3 text-xs font-bold uppercase tracking-wide text-[#475569]">Estado</th></tr></thead>
                        <tbody class="divide-y divide-[#E2E8F0] bg-white">
                            @forelse ($upcomingAppointments as $appointment)
                                <tr class="transition hover:bg-[#F8FAFC]">
                                    <td class="whitespace-nowrap px-5 py-4 text-sm font-bold text-[#0F172A]">{{ $appointment->appointment_date?->format('d/m/Y') }}</td>
                                    <td class="whitespace-nowrap px-5 py-4 text-sm text-[#475569]">{{ substr((string) $appointment->start_time, 0, 5) }}</td>
                                    <td class="px-5 py-4 text-sm font-semibold text-[#0F172A]">{{ $appointment->patient?->full_name }}</td>
                                    <td class="hidden whitespace-nowrap px-5 py-4 text-sm text-[#475569] md:table-cell">{{ $appointment->doctor?->user?->name }}</td>
                                    <td class="hidden whitespace-nowrap px-5 py-4 text-sm text-[#475569] lg:table-cell">{{ $appointment->service?->name ?? 'Sin servicio' }}</td>
                                    <td class="whitespace-nowrap px-5 py-4"><span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-bold {{ $statusClasses[$appointment->status] ?? 'border-slate-200 bg-slate-100 text-slate-600' }}">{{ $statusLabels[$appointment->status] ?? $appointment->status }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-5 py-10 text-center text-sm text-[#475569]">No hay próximas citas programadas.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="rounded-lg border border-[#E2E8F0] bg-white p-5 shadow-sm">
                <h2 class="text-base font-bold text-[#0F172A]">Actividad reciente</h2>
                <p class="mt-2 text-sm leading-6 text-[#475569]">La auditoria y actividad del sistema estaran disponibles en una proxima fase.</p>
            </div>
        </section>
    </div>
</x-app-layout>
