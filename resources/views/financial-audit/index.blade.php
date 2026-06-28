@php
    $timezone = config('app.timezone', 'America/Guayaquil');
    $actionLabels = [
        'payment.paid' => 'Pago marcado como pagado',
        'payment.cancelled' => 'Pago cancelado',
        'payment.refunded' => 'Pago reembolsado',
        'payment.receipt_downloaded' => 'Recibo PDF descargado',
        'payment.receipt_printed' => 'Recibo impreso',
        'report.financial_exported_pdf' => 'Reporte financiero PDF',
        'report.financial_exported_csv' => 'Reporte financiero CSV',
        'report.financial_printed' => 'Reporte financiero impreso',
    ];
@endphp

<x-app-layout>
    <div class="space-y-6">
        <section class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-[#2563EB]">Caja y Finanzas</p>
                <h1 class="mt-2 text-2xl font-bold tracking-tight text-[#0F172A] sm:text-3xl">Registro de caja</h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-[#475569]">Auditoria financiera limitada a pagos, recibos y exportaciones del reporte financiero.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('reports.financial') }}" class="rounded-lg border border-[#E2E8F0] bg-white px-4 py-3 text-sm font-semibold text-[#2563EB] shadow-sm transition hover:border-[#2563EB]">Reporte financiero</a>
                <a href="{{ route('payments.index') }}" class="rounded-lg bg-[#0F172A] px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800">Pagos</a>
            </div>
        </section>

        <section class="grid gap-4 sm:grid-cols-3">
            <article class="rounded-lg border border-[#E2E8F0] bg-white p-5 shadow-sm"><p class="text-sm font-semibold text-[#475569]">Eventos de hoy</p><p class="mt-3 text-2xl font-bold text-[#0F172A]">{{ number_format($eventsToday) }}</p></article>
            <article class="rounded-lg border border-[#E2E8F0] bg-white p-5 shadow-sm"><p class="text-sm font-semibold text-[#475569]">Pagos/recibos hoy</p><p class="mt-3 text-2xl font-bold text-[#0F172A]">{{ number_format($paymentsToday) }}</p></article>
            <article class="rounded-lg border border-[#E2E8F0] bg-white p-5 shadow-sm"><p class="text-sm font-semibold text-[#475569]">Exportaciones hoy</p><p class="mt-3 text-2xl font-bold text-[#0F172A]">{{ number_format($exportsToday) }}</p></article>
        </section>

        <form method="GET" action="{{ route('financial-audit.index') }}" class="rounded-lg border border-[#E2E8F0] bg-white p-5 shadow-sm">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                <div><label for="date_from" class="mb-2 block text-sm font-semibold text-[#0F172A]">Desde</label><input id="date_from" name="date_from" type="date" value="{{ $dateFrom }}" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]"></div>
                <div><label for="date_to" class="mb-2 block text-sm font-semibold text-[#0F172A]">Hasta</label><input id="date_to" name="date_to" type="date" value="{{ $dateTo }}" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]"></div>
                <div><label for="action" class="mb-2 block text-sm font-semibold text-[#0F172A]">Evento</label><select id="action" name="action" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]"><option value="">Todos</option>@foreach($actions as $event)<option value="{{ $event }}" @selected($action === $event)>{{ $actionLabels[$event] ?? $event }}</option>@endforeach</select></div>
                <div class="xl:col-span-2"><label for="search" class="mb-2 block text-sm font-semibold text-[#0F172A]">Buscar</label><input id="search" name="search" type="search" value="{{ $search }}" placeholder="Accion, descripcion o ID" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]"></div>
            </div>
            <div class="mt-5 flex flex-wrap justify-end gap-2"><a href="{{ route('financial-audit.index') }}" class="rounded-lg border border-[#E2E8F0] px-4 py-2.5 text-sm font-semibold text-[#475569]">Limpiar</a><button type="submit" class="rounded-lg bg-[#0F172A] px-4 py-2.5 text-sm font-semibold text-white">Filtrar</button></div>
        </form>

        <section class="overflow-hidden rounded-lg border border-[#E2E8F0] bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-[#E2E8F0] text-left">
                    <thead class="bg-[#F8FAFC]"><tr><th class="px-5 py-3 text-xs font-bold uppercase text-[#475569]">Fecha</th><th class="px-5 py-3 text-xs font-bold uppercase text-[#475569]">Evento</th><th class="px-5 py-3 text-xs font-bold uppercase text-[#475569]">Usuario</th><th class="px-5 py-3 text-xs font-bold uppercase text-[#475569]">Referencia</th><th class="px-5 py-3 text-xs font-bold uppercase text-[#475569]">Detalle</th></tr></thead>
                    <tbody class="divide-y divide-[#E2E8F0]">
                        @forelse($logs as $log)
                            <tr>
                                <td class="whitespace-nowrap px-5 py-4 text-sm font-semibold text-[#0F172A]">{{ $log->created_at?->timezone($timezone)->format('d/m/Y H:i') }}</td>
                                <td class="px-5 py-4 text-sm"><span class="rounded-full bg-[#2563EB]/10 px-2.5 py-1 text-xs font-bold text-[#2563EB]">{{ $actionLabels[$log->action] ?? $log->action }}</span></td>
                                <td class="px-5 py-4 text-sm text-[#475569]">{{ $log->user?->name ?? 'Sistema' }}</td>
                                <td class="px-5 py-4 text-sm text-[#475569]">{{ $log->auditable_label }}</td>
                                <td class="max-w-xl px-5 py-4 text-sm text-[#475569]">
                                    <p>{{ $log->description ?: 'Sin descripcion' }}</p>
                                    @if(is_array($log->new_values) && $log->new_values !== [])
                                        <p class="mt-1 text-xs text-slate-500">{{ collect($log->new_values)->only(['payment_id', 'receipt_number', 'format', 'total_amount', 'total_records'])->filter(fn($value) => $value !== null && $value !== '')->map(fn($value, $key) => $key.': '.$value)->implode(' | ') }}</p>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-5 py-12 text-center text-sm text-[#475569]">No hay eventos financieros para los filtros seleccionados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-[#E2E8F0] px-5 py-4">{{ $logs->links() }}</div>
        </section>
    </div>
</x-app-layout>
