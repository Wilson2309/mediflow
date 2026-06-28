@php
    $patientCount = $patientCount ?? 0;
    $activeDoctorCount = $activeDoctorCount ?? 0;
    $todayAppointmentCount = $todayAppointmentCount ?? 0;
    $activePrescriptionCount = $activePrescriptionCount ?? 0;
    $monthlyPaidIncome = $monthlyPaidIncome ?? 0;
    $pendingPaymentsCount = $pendingPaymentsCount ?? 0;
    $activeServiceCount = $activeServiceCount ?? 0;
    $activeUserCount = $activeUserCount ?? 0;
    $pendingDemoRequestCount = $pendingDemoRequestCount ?? 0;
    $upcomingAppointments = $upcomingAppointments ?? collect();
    $isDoctorDashboard = $isDoctorDashboard ?? false;

    $icons = [
        'calendar' => '<svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 3.75v3M16.5 3.75v3M4.5 9.75h15M6 6h12a1.5 1.5 0 0 1 1.5 1.5V18A2.25 2.25 0 0 1 17.25 20.25H6.75A2.25 2.25 0 0 1 4.5 18V7.5A1.5 1.5 0 0 1 6 6Z" /></svg>',
        'patients' => '<svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 7.5a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 20.25a7.5 7.5 0 0 1 15 0" /></svg>',
        'consultations' => '<svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5h7.5M6.75 5.75h10.5A2.25 2.25 0 0 1 19.5 8v10.25a2.25 2.25 0 0 1-2.25 2.25H6.75a2.25 2.25 0 0 1-2.25-2.25V8a2.25 2.25 0 0 1 2.25-2.25ZM8.25 11.25h7.5M8.25 15h4.5" /></svg>',
        'income' => '<svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 7.5A2.25 2.25 0 0 1 6 5.25h12A2.25 2.25 0 0 1 20.25 7.5v9A2.25 2.25 0 0 1 18 18.75H6A2.25 2.25 0 0 1 3.75 16.5v-9ZM3.75 9.75h16.5M7.5 15h3" /></svg>',
        'pending' => '<svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l3.5 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>',
        'doctors' => '<svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75a3.75 3.75 0 1 0 0 7.5 3.75 3.75 0 0 0 0-7.5ZM4.5 21a7.5 7.5 0 0 1 15 0M18 4.5v4.5M20.25 6.75h-4.5" /></svg>',
        'services' => '<svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 6.75h6.75v6.75H4.5V6.75ZM12.75 5.25h6.75M12.75 9h6.75M12.75 12.75h6.75M4.5 17.25h15" /></svg>',
        'users' => '<svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 7.5a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 20.25a7.5 7.5 0 0 1 15 0M18.75 9.75c1.45.2 2.75 1.55 2.75 3.25" /></svg>',
        'demoRequests' => '<svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 5.25h15A1.5 1.5 0 0 1 21 6.75v10.5a1.5 1.5 0 0 1-1.5 1.5h-15A1.5 1.5 0 0 1 3 17.25V6.75a1.5 1.5 0 0 1 1.5-1.5ZM3.75 7.5 12 13.25 20.25 7.5" /></svg>',
        'plus' => '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14" /></svg>',
    ];

    $stats = [
        ['permission' => 'appointments.view', 'label' => $isDoctorDashboard ? 'Mis citas de hoy' : 'Citas de hoy', 'value' => number_format($todayAppointmentCount), 'summary' => $isDoctorDashboard ? 'Asignadas a tu agenda' : 'Programadas y confirmadas', 'icon' => 'calendar', 'iconClass' => 'bg-[#2563EB] text-white', 'trend' => 'Real'],
        ['permission' => 'patients.view', 'label' => 'Pacientes registrados', 'value' => number_format($patientCount), 'summary' => 'Pacientes de tu clinica', 'icon' => 'patients', 'iconClass' => 'bg-[#38BDF8]/15 text-[#2563EB]', 'trend' => 'Real'],
        ['permission' => 'consultations.view', 'label' => 'Consultas realizadas', 'value' => $consultationCount ?? 0, 'summary' => 'Atenciones registradas', 'icon' => 'consultations', 'iconClass' => 'bg-[#10B981]/15 text-[#10B981]', 'trend' => 'Real'],
        ['permission' => ['payments.view', 'reports.financial'], 'label' => 'Ingresos del mes', 'value' => '$'.number_format((float) $monthlyPaidIncome, 2), 'summary' => 'Pagos marcados como pagados', 'icon' => 'income', 'iconClass' => 'bg-[#0F172A] text-white', 'trend' => 'Real'],
        ['permission' => ['payments.view', 'reports.financial'], 'label' => 'Pagos pendientes', 'value' => number_format($pendingPaymentsCount), 'summary' => 'Cobros por confirmar', 'icon' => 'pending', 'iconClass' => 'bg-[#F59E0B]/15 text-[#F59E0B]', 'trend' => 'Real'],
        ['permission' => 'doctors.view', 'label' => 'Medicos activos', 'value' => number_format($activeDoctorCount), 'summary' => 'Profesionales activos', 'icon' => 'doctors', 'iconClass' => 'bg-[#EF4444]/10 text-[#EF4444]', 'trend' => 'Real'],
        ['permission' => 'services.view', 'label' => 'Servicios activos', 'value' => number_format($activeServiceCount), 'summary' => 'Servicios disponibles', 'icon' => 'services', 'iconClass' => 'bg-[#2563EB]/10 text-[#2563EB]', 'trend' => 'Real'],
        ['permission' => 'users.view', 'label' => 'Usuarios activos', 'value' => number_format($activeUserCount), 'summary' => 'Usuarios de tu clinica', 'icon' => 'users', 'iconClass' => 'bg-[#38BDF8]/15 text-[#2563EB]', 'trend' => 'Real'],
        ['permission' => 'demo_requests.view', 'label' => 'Solicitudes de demo', 'value' => number_format($pendingDemoRequestCount), 'summary' => 'Prospectos pendientes', 'icon' => 'demoRequests', 'iconClass' => 'bg-[#F59E0B]/15 text-[#B45309]', 'trend' => 'Real'],
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
                <p class="mt-2 max-w-2xl text-sm leading-6 text-[#475569]">Vista operativa para seguimiento clinico, agenda medica y control financiero del consultorio.</p>
            </div>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <div class="rounded-lg border border-[#E2E8F0] bg-white px-4 py-3 text-sm font-semibold text-[#475569] shadow-sm">{{ now(config('app.timezone', 'America/Guayaquil'))->format('d/m/Y') }}</div>
                @can('appointments.view')
                    <a href="{{ route('daily-agenda.index') }}" class="inline-flex items-center justify-center gap-2 rounded-lg border border-[#E2E8F0] bg-white px-4 py-3 text-sm font-semibold text-[#2563EB] shadow-sm transition hover:border-[#2563EB] hover:bg-[#2563EB]/5">Agenda del dia</a>
                @endcan
                @can('patients.create')
                    <a href="{{ route('patients.create') }}" class="inline-flex items-center justify-center gap-2 rounded-lg border border-[#E2E8F0] bg-white px-4 py-3 text-sm font-semibold text-[#0F172A] shadow-sm transition hover:border-[#2563EB] hover:text-[#2563EB]">Nuevo paciente</a>
                @endcan
                @can('payments.view')
                    <a href="{{ route('payments.index', ['payment_status' => 'pending']) }}" class="inline-flex items-center justify-center gap-2 rounded-lg border border-[#F59E0B]/30 bg-[#F59E0B]/10 px-4 py-3 text-sm font-semibold text-[#B45309] shadow-sm transition hover:bg-[#F59E0B]/15">Pendientes de cobro</a>
                @endcan
                @can('appointments.create')
                    <a href="{{ route('appointments.create') }}" class="inline-flex items-center justify-center gap-2 rounded-lg bg-[#2563EB] px-4 py-3 text-sm font-semibold text-white shadow-sm shadow-blue-500/20 transition hover:bg-blue-700">{!! $icons['plus'] !!} Nueva cita</a>
                @endcan
            </div>
        </section>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($stats as $stat)
                @canany((array) $stat['permission'])
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
                @endcanany
            @endforeach
        </section>

        @can('appointments.view')
        <section class="grid gap-6 xl:grid-cols-3">
            <div class="rounded-lg border border-[#E2E8F0] bg-white shadow-sm xl:col-span-2">
                <div class="flex flex-col gap-3 border-b border-[#E2E8F0] px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div><h2 class="text-base font-bold text-[#0F172A]">{{ $isDoctorDashboard ? 'Mis prÃ³ximas citas' : 'PrÃ³ximas citas' }}</h2><p class="mt-1 text-sm text-[#475569]">{{ $isDoctorDashboard ? 'Agenda medica asignada para los prÃ³ximos dÃ­as.' : 'Agenda clinica priorizada para los prÃ³ximos dÃ­as.' }}</p></div>
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
                                <tr><td colspan="6" class="px-5 py-10 text-center text-sm text-[#475569]">No hay prÃ³ximas citas programadas.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="rounded-lg border border-[#E2E8F0] bg-white p-5 shadow-sm">
                <h2 class="text-base font-bold text-[#0F172A]">Accesos operativos</h2>
                <p class="mt-2 text-sm leading-6 text-[#475569]">Funciones disponibles para el flujo diario de recepcion, caja y seguimiento financiero.</p>
                <div class="mt-5 grid gap-2">
                    @can('appointments.view')
                        <a href="{{ route('daily-agenda.index') }}" class="rounded-lg border border-[#E2E8F0] px-3 py-2 text-sm font-semibold text-[#2563EB] transition hover:border-[#2563EB] hover:bg-[#2563EB]/5">Agenda del dia</a>
                    @endcan
                    @can('payments.view')
                        <a href="{{ route('payments.index', ['payment_status' => 'pending']) }}" class="rounded-lg border border-[#E2E8F0] px-3 py-2 text-sm font-semibold text-[#B45309] transition hover:border-[#F59E0B] hover:bg-[#F59E0B]/5">Pendientes de cobro</a>
                        <a href="{{ route('payments.index', ['payment_status' => 'paid', 'date_from' => now(config('app.timezone', 'America/Guayaquil'))->toDateString(), 'date_to' => now(config('app.timezone', 'America/Guayaquil'))->toDateString()]) }}" class="rounded-lg border border-[#E2E8F0] px-3 py-2 text-sm font-semibold text-[#047857] transition hover:border-[#10B981] hover:bg-[#10B981]/5">Pagos del dia</a>
                        <a href="{{ route('payments.index', ['payment_status' => 'paid']) }}" class="rounded-lg border border-[#E2E8F0] px-3 py-2 text-sm font-semibold text-[#0F172A] transition hover:border-[#0F172A] hover:bg-slate-50">Recibos de pago</a>
                    @endcan
                    @can('reports.financial')
                        <a href="{{ route('reports.financial') }}" class="rounded-lg border border-[#E2E8F0] px-3 py-2 text-sm font-semibold text-[#2563EB] transition hover:border-[#2563EB] hover:bg-[#2563EB]/5">Reporte financiero</a>
                        <a href="{{ route('financial-audit.index') }}" class="rounded-lg border border-[#E2E8F0] px-3 py-2 text-sm font-semibold text-[#475569] transition hover:border-[#2563EB] hover:text-[#2563EB]">Registro de caja</a>
                    @endcan
                </div>
            </div>
        </section>
        @endcan
    </div>
</x-app-layout>
