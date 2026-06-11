@php
    $methodLabels = ['cash' => 'Efectivo', 'card' => 'Tarjeta', 'transfer' => 'Transferencia', 'other' => 'Otro'];
    $statusLabels = ['pending' => 'Pendiente', 'paid' => 'Pagado', 'cancelled' => 'Cancelado', 'refunded' => 'Reembolsado'];
    $statusClasses = ['pending' => 'bg-[#F59E0B]/10 text-[#B45309]', 'paid' => 'bg-[#10B981]/10 text-[#047857]', 'cancelled' => 'bg-[#EF4444]/10 text-[#EF4444]', 'refunded' => 'bg-slate-100 text-[#475569]'];
@endphp
<x-app-layout><div class="space-y-6">
    @include('reports._header', ['title' => 'Reporte financiero', 'description' => 'Ingresos reales, cartera pendiente, métodos de pago y rentabilidad por servicio.'])
    @include('reports._filters', ['routeName' => 'reports.financial', 'showService' => true, 'showPaymentStatus' => true, 'showPaymentMethod' => true])
    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @include('reports._stat-card', ['label' => 'Ingresos pagados', 'value' => '$'.number_format($metrics['paidIncome'],2), 'tone' => 'green', 'summary' => 'No incluye pagos pendientes'])
        @include('reports._stat-card', ['label' => 'Pagos pendientes', 'value' => number_format($metrics['pending']), 'tone' => 'yellow'])
        @include('reports._stat-card', ['label' => 'Cancelados', 'value' => number_format($metrics['cancelled']), 'tone' => 'red'])
        @include('reports._stat-card', ['label' => 'Reembolsados', 'value' => number_format($metrics['refunded']), 'tone' => 'slate'])
        @include('reports._stat-card', ['label' => 'Pago promedio', 'value' => '$'.number_format($metrics['averagePayment'],2)])
        @include('reports._stat-card', ['label' => 'Pacientes con deuda', 'value' => number_format($metrics['patientsWithPendingPayments']), 'tone' => 'yellow'])
    </section>
    <section class="grid gap-6 xl:grid-cols-2">
        <article class="rounded-lg border border-[#E2E8F0] bg-white p-5 shadow-sm"><h2 class="font-bold text-[#0F172A]">Pagos por método</h2><div class="mt-4 grid gap-3 sm:grid-cols-2">@foreach($methodLabels as $key=>$label)<div class="rounded-lg bg-[#F8FAFC] p-4"><p class="text-sm text-[#475569]">{{ $label }}</p><p class="mt-2 text-xl font-bold">{{ number_format($methodCounts[$key] ?? 0) }}</p></div>@endforeach</div></article>
        <article class="rounded-lg border border-[#E2E8F0] bg-white p-5 shadow-sm"><h2 class="font-bold text-[#0F172A]">Ingresos por servicio</h2><div class="mt-4 divide-y divide-[#E2E8F0]">@forelse($incomeByService as $row)<div class="flex justify-between gap-3 py-3 text-sm"><span class="font-semibold">{{ $row->service?->name ?? 'Sin servicio' }}</span><span class="font-bold text-[#10B981]">${{ number_format((float)$row->total,2) }}</span></div>@empty @include('reports._empty-state') @endforelse</div></article>
    </section>
    <section class="overflow-hidden rounded-lg border border-[#E2E8F0] bg-white shadow-sm"><div class="overflow-x-auto"><table class="min-w-full divide-y divide-[#E2E8F0]"><thead class="bg-[#F8FAFC]"><tr>@foreach(['Fecha de pago','Paciente','Servicio','Monto','Método','Estado','Notas'] as $heading)<th class="px-5 py-3 text-left text-xs font-bold uppercase text-[#475569]">{{ $heading }}</th>@endforeach</tr></thead><tbody class="divide-y divide-[#E2E8F0]">@forelse($payments as $payment)<tr><td class="whitespace-nowrap px-5 py-4 text-sm">{{ $payment->payment_date?->format('d/m/Y H:i') ?? 'Sin fecha' }}</td><td class="px-5 py-4 text-sm font-semibold">{{ $payment->patient?->full_name }}</td><td class="px-5 py-4 text-sm">{{ $payment->service?->name ?? 'Sin servicio' }}</td><td class="px-5 py-4 text-sm font-bold">${{ number_format((float)$payment->amount,2) }}</td><td class="px-5 py-4 text-sm">{{ $methodLabels[$payment->payment_method] }}</td><td class="px-5 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $statusClasses[$payment->payment_status] }}">{{ $statusLabels[$payment->payment_status] }}</span></td><td class="max-w-xs truncate px-5 py-4 text-sm text-[#475569]">{{ $payment->notes ?: 'Sin notas' }}</td></tr>@empty<tr><td colspan="7">@include('reports._empty-state')</td></tr>@endforelse</tbody></table></div><div class="border-t border-[#E2E8F0] px-5 py-4">{{ $payments->links() }}</div></section>
</div></x-app-layout>
