@php
    $appointmentLabels = ['scheduled' => 'Programadas', 'confirmed' => 'Confirmadas', 'completed' => 'Completadas', 'cancelled' => 'Canceladas', 'no_show' => 'No asistió'];
    $appointmentClasses = ['scheduled' => 'bg-[#2563EB]', 'confirmed' => 'bg-[#10B981]', 'completed' => 'bg-[#0F172A]', 'cancelled' => 'bg-[#EF4444]', 'no_show' => 'bg-[#F59E0B]'];
    $maxAppointmentStatus = max(1, (int) collect($appointmentStatusCounts)->max());
@endphp
<x-app-layout>
    <div class="space-y-6">
        @include('reports._header', ['title' => 'Resumen ejecutivo', 'description' => 'Indicadores clínicos, administrativos y financieros consolidados del consultorio.'])
        @include('reports._filters', ['routeName' => 'reports.index'])

        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ([
                ['route' => 'reports.appointments', 'title' => 'Reporte de citas', 'text' => 'Agenda, estados y asistencia.'],
                ['route' => 'reports.clinical', 'title' => 'Reporte clínico', 'text' => 'Consultas, recetas e historiales.'],
                ['route' => 'reports.financial', 'title' => 'Reporte financiero', 'text' => 'Ingresos, cartera y métodos de pago.'],
                ['route' => 'reports.patients', 'title' => 'Reporte de pacientes', 'text' => 'Crecimiento y actividad de pacientes.'],
                ['route' => 'reports.doctors', 'title' => 'Reporte de médicos', 'text' => 'Rendimiento y producción médica.'],
                ['route' => 'reports.services', 'title' => 'Reporte de servicios', 'text' => 'Uso, precios e ingresos por servicio.'],
            ] as $report)
                <a href="{{ route($report['route'], ['start_date' => $startDate, 'end_date' => $endDate]) }}" class="group rounded-lg border border-[#E2E8F0] bg-white p-4 shadow-sm transition hover:border-[#2563EB] hover:shadow-md">
                    <p class="font-bold text-[#0F172A] group-hover:text-[#2563EB]">{{ $report['title'] }}</p>
                    <p class="mt-1 text-sm text-[#475569]">{{ $report['text'] }}</p>
                </a>
            @endforeach
        </section>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @include('reports._stat-card', ['label' => 'Pacientes activos', 'value' => number_format($metrics['activePatients']), 'summary' => number_format($metrics['newPatients']).' nuevos en el periodo'])
            @include('reports._stat-card', ['label' => 'Médicos activos', 'value' => number_format($metrics['activeDoctors']), 'tone' => 'green'])
            @include('reports._stat-card', ['label' => 'Servicios activos', 'value' => number_format($metrics['activeServices']), 'tone' => 'slate'])
            @include('reports._stat-card', ['label' => 'Citas del periodo', 'value' => number_format($metrics['appointments']), 'summary' => number_format($metrics['todayAppointments']).' citas de hoy'])
            @include('reports._stat-card', ['label' => 'Consultas realizadas', 'value' => number_format($metrics['consultations']), 'tone' => 'green'])
            @include('reports._stat-card', ['label' => 'Recetas emitidas', 'value' => number_format($metrics['prescriptions']), 'tone' => 'blue'])
            @include('reports._stat-card', ['label' => 'Ingresos pagados', 'value' => '$'.number_format($metrics['paidIncome'], 2), 'tone' => 'green', 'summary' => 'Solo pagos con estado pagado'])
            @include('reports._stat-card', ['label' => 'Pagos pendientes', 'value' => number_format($metrics['pendingPayments']), 'tone' => 'yellow', 'summary' => number_format($metrics['cancelledOrRefundedPayments']).' cancelados o reembolsados'])
        </section>

        <section class="grid gap-6 xl:grid-cols-3">
            <article class="rounded-lg border border-[#E2E8F0] bg-white p-5 shadow-sm">
                <h2 class="text-base font-bold text-[#0F172A]">Citas por estado</h2><p class="mt-1 text-sm text-[#475569]">Distribución operativa del periodo.</p>
                <div class="mt-5 space-y-4">@foreach ($appointmentLabels as $status => $label)<div><div class="mb-1 flex justify-between text-sm"><span class="font-semibold text-[#475569]">{{ $label }}</span><span class="font-bold text-[#0F172A]">{{ number_format($appointmentStatusCounts[$status] ?? 0) }}</span></div><div class="h-2 rounded-full bg-slate-100"><div class="h-2 rounded-full {{ $appointmentClasses[$status] }}" style="width: {{ (($appointmentStatusCounts[$status] ?? 0) / $maxAppointmentStatus) * 100 }}%"></div></div></div>@endforeach</div>
            </article>
            <article class="rounded-lg border border-[#E2E8F0] bg-white p-5 shadow-sm">
                <h2 class="text-base font-bold text-[#0F172A]">Servicios más usados</h2><p class="mt-1 text-sm text-[#475569]">Según citas asociadas.</p>
                <div class="mt-5 divide-y divide-[#E2E8F0]">@forelse ($topServices as $row)<div class="flex items-center justify-between gap-3 py-3"><span class="text-sm font-semibold text-[#0F172A]">{{ $row->service?->name ?? 'Sin servicio' }}</span><span class="rounded-full bg-[#2563EB]/10 px-2.5 py-1 text-xs font-bold text-[#2563EB]">{{ $row->total }}</span></div>@empty @include('reports._empty-state') @endforelse</div>
            </article>
            <article class="rounded-lg border border-[#E2E8F0] bg-white p-5 shadow-sm">
                <h2 class="text-base font-bold text-[#0F172A]">Médicos con más consultas</h2><p class="mt-1 text-sm text-[#475569]">Actividad clínica registrada.</p>
                <div class="mt-5 divide-y divide-[#E2E8F0]">@forelse ($topDoctors as $row)<div class="flex items-center justify-between gap-3 py-3"><span class="text-sm font-semibold text-[#0F172A]">{{ $row->doctor?->user?->name ?? 'Médico sin usuario' }}</span><span class="rounded-full bg-[#10B981]/10 px-2.5 py-1 text-xs font-bold text-[#047857]">{{ $row->total }}</span></div>@empty @include('reports._empty-state') @endforelse</div>
            </article>
        </section>

        <section class="grid gap-6 xl:grid-cols-2">
            <article class="overflow-hidden rounded-lg border border-[#E2E8F0] bg-white shadow-sm"><div class="border-b border-[#E2E8F0] px-5 py-4"><h2 class="font-bold text-[#0F172A]">Últimas citas</h2></div><div class="overflow-x-auto"><table class="min-w-full divide-y divide-[#E2E8F0]"><thead class="bg-[#F8FAFC]"><tr><th class="px-5 py-3 text-left text-xs font-bold uppercase text-[#475569]">Fecha</th><th class="px-5 py-3 text-left text-xs font-bold uppercase text-[#475569]">Paciente</th><th class="px-5 py-3 text-left text-xs font-bold uppercase text-[#475569]">Médico</th><th class="px-5 py-3 text-left text-xs font-bold uppercase text-[#475569]">Estado</th></tr></thead><tbody class="divide-y divide-[#E2E8F0]">@forelse ($latestAppointments as $appointment)<tr><td class="px-5 py-3 text-sm text-[#475569]">{{ $appointment->appointment_date?->format('d/m/Y') }}</td><td class="px-5 py-3 text-sm font-semibold text-[#0F172A]">{{ $appointment->patient?->full_name }}</td><td class="px-5 py-3 text-sm text-[#475569]">{{ $appointment->doctor?->user?->name }}</td><td class="px-5 py-3 text-sm text-[#475569]">{{ $appointmentLabels[$appointment->status] ?? $appointment->status }}</td></tr>@empty<tr><td colspan="4">@include('reports._empty-state')</td></tr>@endforelse</tbody></table></div></article>
            <article class="overflow-hidden rounded-lg border border-[#E2E8F0] bg-white shadow-sm"><div class="border-b border-[#E2E8F0] px-5 py-4"><h2 class="font-bold text-[#0F172A]">Últimos pagos</h2></div><div class="overflow-x-auto"><table class="min-w-full divide-y divide-[#E2E8F0]"><thead class="bg-[#F8FAFC]"><tr><th class="px-5 py-3 text-left text-xs font-bold uppercase text-[#475569]">Paciente</th><th class="px-5 py-3 text-left text-xs font-bold uppercase text-[#475569]">Servicio</th><th class="px-5 py-3 text-left text-xs font-bold uppercase text-[#475569]">Monto</th><th class="px-5 py-3 text-left text-xs font-bold uppercase text-[#475569]">Estado</th></tr></thead><tbody class="divide-y divide-[#E2E8F0]">@forelse ($latestPayments as $payment)<tr><td class="px-5 py-3 text-sm font-semibold text-[#0F172A]">{{ $payment->patient?->full_name }}</td><td class="px-5 py-3 text-sm text-[#475569]">{{ $payment->service?->name ?? 'Sin servicio' }}</td><td class="px-5 py-3 text-sm font-bold text-[#0F172A]">${{ number_format((float) $payment->amount, 2) }}</td><td class="px-5 py-3 text-sm text-[#475569]">{{ ucfirst($payment->payment_status) }}</td></tr>@empty<tr><td colspan="4">@include('reports._empty-state')</td></tr>@endforelse</tbody></table></div></article>
        </section>
    </div>
</x-app-layout>
