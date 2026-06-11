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
                <p class="text-sm font-semibold uppercase tracking-wide text-[#2563EB]">Ficha de pago</p>
                <h1 class="mt-2 text-2xl font-bold tracking-tight text-[#0F172A] sm:text-3xl">${{ number_format((float) $payment->amount, 2) }}</h1>
                <p class="mt-2 text-sm text-[#475569]">{{ $payment->patient?->full_name }}</p>
            </div>
            <div class="flex flex-col gap-3 sm:flex-row">
                <a href="{{ route('payments.index') }}" class="inline-flex items-center justify-center rounded-lg border border-[#E2E8F0] px-4 py-3 text-sm font-semibold text-[#475569]">Volver</a>
                <button type="button" disabled class="inline-flex cursor-not-allowed items-center justify-center rounded-lg border border-[#E2E8F0] px-4 py-3 text-sm font-semibold text-[#94A3B8]">Recibo PDF próximamente</button>
                <a href="{{ route('payments.edit', $payment) }}" class="inline-flex items-center justify-center rounded-lg bg-[#2563EB] px-4 py-3 text-sm font-semibold text-white shadow-sm shadow-blue-500/20">Editar pago</a>
            </div>
        </header>

        @if (session('success'))
            <div class="rounded-lg border border-[#10B981]/30 bg-[#10B981]/10 px-4 py-3 text-sm font-semibold text-[#047857]">{{ session('success') }}</div>
        @endif

        <section class="grid gap-5 lg:grid-cols-3">
            <article class="rounded-lg border border-[#E2E8F0] bg-white p-5 shadow-sm">
                <h2 class="text-base font-bold text-[#0F172A]">Información del pago</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div><dt class="font-semibold text-[#475569]">Monto</dt><dd class="mt-1 text-[#0F172A]">${{ number_format((float) $payment->amount, 2) }}</dd></div>
                    <div><dt class="font-semibold text-[#475569]">Método de pago</dt><dd class="mt-1 text-[#0F172A]">{{ $methodLabels[$payment->payment_method] ?? $payment->payment_method }}</dd></div>
                    <div><dt class="font-semibold text-[#475569]">Estado del pago</dt><dd class="mt-1"><span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-bold {{ $statusClasses[$payment->payment_status] ?? 'border-slate-200 bg-slate-100 text-slate-600' }}">{{ $statusLabels[$payment->payment_status] ?? $payment->payment_status }}</span></dd></div>
                    <div><dt class="font-semibold text-[#475569]">Fecha de pago</dt><dd class="mt-1 text-[#0F172A]">{{ $payment->payment_date?->format('d/m/Y H:i') ?: 'Sin fecha' }}</dd></div>
                </dl>
            </article>
            <article class="rounded-lg border border-[#E2E8F0] bg-white p-5 shadow-sm">
                <h2 class="text-base font-bold text-[#0F172A]">Paciente</h2>
                <p class="mt-4 text-sm font-semibold text-[#0F172A]">{{ $payment->patient?->full_name }}</p>
                <p class="mt-1 text-sm text-[#475569]">{{ $payment->patient?->identification_number ?: 'Sin identificación' }}</p>
                <p class="mt-1 text-sm text-[#475569]">{{ $payment->patient?->phone ?: 'Sin teléfono' }}</p>
            </article>
            <article class="rounded-lg border border-[#E2E8F0] bg-white p-5 shadow-sm">
                <h2 class="text-base font-bold text-[#0F172A]">Fechas</h2>
                <p class="mt-4 text-sm text-[#475569]">Creado: <span class="font-semibold text-[#0F172A]">{{ $payment->created_at?->format('d/m/Y H:i') }}</span></p>
                <p class="mt-2 text-sm text-[#475569]">Actualizado: <span class="font-semibold text-[#0F172A]">{{ $payment->updated_at?->format('d/m/Y H:i') }}</span></p>
            </article>
        </section>

        <section class="grid gap-5 lg:grid-cols-2">
            <article class="rounded-lg border border-[#E2E8F0] bg-white p-5 shadow-sm">
                <h2 class="text-base font-bold text-[#0F172A]">Cita asociada</h2>
                @if ($payment->appointment)
                    <p class="mt-3 text-sm text-[#475569]">{{ $payment->appointment->appointment_date?->format('d/m/Y') }} · {{ substr((string) $payment->appointment->start_time, 0, 5) }} · {{ $payment->appointment->doctor?->user?->name }}</p>
                @else
                    <p class="mt-3 text-sm text-[#475569]">Pago registrado sin cita asociada.</p>
                @endif
            </article>
            <article class="rounded-lg border border-[#E2E8F0] bg-white p-5 shadow-sm">
                <h2 class="text-base font-bold text-[#0F172A]">Servicio asociado</h2>
                <p class="mt-3 text-sm text-[#475569]">{{ $payment->service?->name ?: 'Sin servicio asociado' }}</p>
            </article>
        </section>

        <section class="rounded-lg border border-[#E2E8F0] bg-white p-5 shadow-sm">
            <h2 class="text-base font-bold text-[#0F172A]">Notas</h2>
            <p class="mt-3 whitespace-pre-line text-sm leading-6 text-[#475569]">{{ $payment->notes ?: 'Sin notas registradas.' }}</p>
        </section>
    </div>
</x-app-layout>
