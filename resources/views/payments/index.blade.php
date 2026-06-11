@php
    $methodLabels = ['cash' => 'Efectivo', 'card' => 'Tarjeta', 'transfer' => 'Transferencia', 'other' => 'Otro'];
    $statusLabels = ['pending' => 'Pendiente', 'paid' => 'Pagado', 'cancelled' => 'Cancelado', 'refunded' => 'Reembolsado'];
    $statusClasses = [
        'pending' => 'border-[#F59E0B]/20 bg-[#F59E0B]/10 text-[#F59E0B]',
        'paid' => 'border-[#10B981]/20 bg-[#10B981]/10 text-[#10B981]',
        'cancelled' => 'border-[#EF4444]/20 bg-[#EF4444]/10 text-[#EF4444]',
        'refunded' => 'border-slate-200 bg-slate-100 text-[#475569]',
    ];
@endphp

<x-app-layout>
    <div class="space-y-6">
        <header class="flex flex-col gap-4 rounded-lg border border-[#E2E8F0] bg-white p-5 shadow-sm lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-[#2563EB]">Finanzas</p>
                <h1 class="mt-2 text-2xl font-bold tracking-tight text-[#0F172A] sm:text-3xl">Pagos y Finanzas</h1>
                <p class="mt-2 text-sm leading-6 text-[#475569]">Gestión de pagos, ingresos y estados financieros del consultorio</p>
            </div>
            <a href="{{ route('payments.create') }}" class="inline-flex items-center justify-center rounded-lg bg-[#2563EB] px-4 py-3 text-sm font-semibold text-white shadow-sm shadow-blue-500/20 transition hover:bg-blue-700">Nuevo pago</a>
        </header>

        @if (session('success'))
            <div class="rounded-lg border border-[#10B981]/30 bg-[#10B981]/10 px-4 py-3 text-sm font-semibold text-[#047857]">{{ session('success') }}</div>
        @endif

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <article class="rounded-lg border border-[#E2E8F0] bg-white p-5 shadow-sm"><p class="text-sm font-semibold text-[#475569]">Ingresos pagados del mes</p><p class="mt-3 text-2xl font-bold text-[#0F172A]">${{ number_format((float) $monthlyPaidIncome, 2) }}</p></article>
            <article class="rounded-lg border border-[#E2E8F0] bg-white p-5 shadow-sm"><p class="text-sm font-semibold text-[#475569]">Pagos pendientes</p><p class="mt-3 text-2xl font-bold text-[#F59E0B]">{{ number_format($pendingPaymentsCount) }}</p></article>
            <article class="rounded-lg border border-[#E2E8F0] bg-white p-5 shadow-sm"><p class="text-sm font-semibold text-[#475569]">Total pagado histórico</p><p class="mt-3 text-2xl font-bold text-[#10B981]">${{ number_format((float) $totalPaidIncome, 2) }}</p></article>
            <article class="rounded-lg border border-[#E2E8F0] bg-white p-5 shadow-sm"><p class="text-sm font-semibold text-[#475569]">Cancelados/Reembolsados</p><p class="mt-3 text-2xl font-bold text-[#EF4444]">{{ number_format($cancelledOrRefundedCount) }}</p></article>
        </section>

        <form method="GET" action="{{ route('payments.index') }}" class="rounded-lg border border-[#E2E8F0] bg-white p-5 shadow-sm">
            <div class="grid gap-4 md:grid-cols-6">
                <div class="md:col-span-2">
                    <label for="search" class="mb-2 block text-sm font-semibold text-[#0F172A]">Buscar</label>
                    <input id="search" name="search" type="search" value="{{ $search }}" placeholder="Paciente, servicio o notas" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
                </div>
                <div>
                    <label for="payment_status" class="mb-2 block text-sm font-semibold text-[#0F172A]">Estado</label>
                    <select id="payment_status" name="payment_status" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
                        <option value="">Todos</option>
                        @foreach ($statusLabels as $value => $label)
                            <option value="{{ $value }}" @selected($paymentStatus === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="payment_method" class="mb-2 block text-sm font-semibold text-[#0F172A]">Método</label>
                    <select id="payment_method" name="payment_method" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
                        <option value="">Todos</option>
                        @foreach ($methodLabels as $value => $label)
                            <option value="{{ $value }}" @selected($paymentMethod === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="date_from" class="mb-2 block text-sm font-semibold text-[#0F172A]">Desde</label>
                    <input id="date_from" name="date_from" type="date" value="{{ $dateFrom }}" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
                </div>
                <div>
                    <label for="date_to" class="mb-2 block text-sm font-semibold text-[#0F172A]">Hasta</label>
                    <input id="date_to" name="date_to" type="date" value="{{ $dateTo }}" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
                </div>
            </div>
            <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:justify-end">
                <a href="{{ route('payments.index') }}" class="inline-flex items-center justify-center rounded-lg border border-[#E2E8F0] px-4 py-2.5 text-sm font-semibold text-[#475569]">Limpiar</a>
                <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-[#0F172A] px-4 py-2.5 text-sm font-semibold text-white">Filtrar</button>
            </div>
        </form>

        <section class="overflow-hidden rounded-lg border border-[#E2E8F0] bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-[#E2E8F0]">
                    <thead class="bg-[#F8FAFC]">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-[#475569]">Fecha de pago</th>
                            <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-[#475569]">Paciente</th>
                            <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-[#475569]">Servicio</th>
                            <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-[#475569]">Cita asociada</th>
                            <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-[#475569]">Monto</th>
                            <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-[#475569]">Método</th>
                            <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-[#475569]">Estado</th>
                            <th class="px-5 py-3 text-right text-xs font-bold uppercase tracking-wide text-[#475569]">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E2E8F0] bg-white">
                        @forelse ($payments as $payment)
                            <tr class="hover:bg-[#F8FAFC]">
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-[#0F172A]">{{ $payment->payment_date?->format('d/m/Y H:i') ?: 'Sin fecha' }}</td>
                                <td class="px-5 py-4 text-sm font-semibold text-[#0F172A]">{{ $payment->patient?->full_name }}</td>
                                <td class="px-5 py-4 text-sm text-[#475569]">{{ $payment->service?->name ?: 'Sin servicio' }}</td>
                                <td class="px-5 py-4 text-sm text-[#475569]">{{ $payment->appointment ? $payment->appointment->appointment_date?->format('d/m/Y').' '.substr((string) $payment->appointment->start_time, 0, 5) : 'Sin cita' }}</td>
                                <td class="px-5 py-4 text-sm font-bold text-[#0F172A]">${{ number_format((float) $payment->amount, 2) }}</td>
                                <td class="px-5 py-4 text-sm text-[#475569]">{{ $methodLabels[$payment->payment_method] ?? $payment->payment_method }}</td>
                                <td class="px-5 py-4"><span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-bold {{ $statusClasses[$payment->payment_status] ?? 'border-slate-200 bg-slate-100 text-slate-600' }}">{{ $statusLabels[$payment->payment_status] ?? $payment->payment_status }}</span></td>
                                <td class="px-5 py-4">
                                    <div class="flex justify-end gap-2">
                                        <a href="{{ route('payments.show', $payment) }}" class="rounded-lg border border-[#E2E8F0] px-3 py-2 text-xs font-semibold text-[#2563EB]">Ver</a>
                                        <a href="{{ route('payments.edit', $payment) }}" class="rounded-lg border border-[#E2E8F0] px-3 py-2 text-xs font-semibold text-[#475569]">Editar</a>
                                        <form method="POST" action="{{ route('payments.destroy', $payment) }}" onsubmit="return confirm('¿Eliminar este pago?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="rounded-lg border border-[#EF4444]/30 px-3 py-2 text-xs font-semibold text-[#EF4444]">Eliminar</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-5 py-10 text-center text-sm text-[#475569]">No hay pagos registrados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-[#E2E8F0] px-5 py-4">{{ $payments->links() }}</div>
        </section>
    </div>
</x-app-layout>
