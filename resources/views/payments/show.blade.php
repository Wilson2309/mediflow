@php
    $methodLabels = ['cash' => 'Efectivo', 'card' => 'Tarjeta', 'transfer' => 'Transferencia', 'other' => 'Otro'];
    $statusLabels = ['pending' => 'Pendiente', 'paid' => 'Pagado', 'cancelled' => 'Cancelado', 'refunded' => 'Reembolsado'];
    $appointmentStatusLabels = ['scheduled' => 'Programada', 'confirmed' => 'Confirmada', 'completed' => 'Completada', 'cancelled' => 'Cancelada', 'no_show' => 'No asistio'];
    $statusClasses = [
        'pending' => 'border-[#F59E0B]/20 bg-[#F59E0B]/10 text-[#B45309]',
        'paid' => 'border-[#10B981]/20 bg-[#10B981]/10 text-[#047857]',
        'cancelled' => 'border-[#EF4444]/20 bg-[#EF4444]/10 text-[#B91C1C]',
        'refunded' => 'border-slate-200 bg-slate-100 text-[#475569]',
    ];
    $timezone = config('app.timezone', 'America/Guayaquil');
    $appointment = $payment->appointment;
    $patient = $payment->patient;
    $service = $payment->service ?: $appointment?->service;
    $doctorName = $appointment?->doctor?->user?->name ?: 'Sin medico asociado';
    $paymentDate = $payment->payment_date?->timezone($timezone);
    $createdAt = $payment->created_at?->timezone($timezone);
@endphp

<x-app-layout>
    <div class="space-y-6">
        <header class="rounded-lg border border-[#E2E8F0] bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <div class="flex flex-wrap items-center gap-3">
                        <p class="text-sm font-semibold uppercase tracking-wide text-[#2563EB]">Ficha de pago</p>
                        <span class="inline-flex rounded-full border px-3 py-1 text-xs font-bold {{ $statusClasses[$payment->payment_status] ?? 'border-slate-200 bg-slate-100 text-slate-600' }}">
                            {{ $statusLabels[$payment->payment_status] ?? $payment->payment_status }}
                        </span>
                    </div>
                    <h1 class="mt-3 text-2xl font-bold tracking-tight text-[#0F172A] sm:text-3xl">{{ $receiptNumber }}</h1>
                    <p class="mt-2 text-sm leading-6 text-[#475569]">
                        {{ $patient?->full_name ?: 'Paciente no disponible' }} - {{ $service?->name ?: 'Sin servicio asociado' }}
                    </p>
                </div>
                <div class="rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-5 py-4 text-right">
                    <p class="text-xs font-bold uppercase tracking-wide text-[#475569]">Monto</p>
                    <p class="mt-2 text-3xl font-bold text-[#0F172A]">${{ number_format((float) $payment->amount, 2) }}</p>
                </div>
            </div>

            <div class="mt-5 flex flex-col gap-3 sm:flex-row sm:flex-wrap">
                <a href="{{ route('payments.index') }}" class="inline-flex items-center justify-center rounded-lg border border-[#E2E8F0] px-4 py-3 text-sm font-semibold text-[#475569]">Volver a pagos</a>
                @can('payments.update')
                    <a href="{{ route('payments.edit', $payment) }}" class="inline-flex items-center justify-center rounded-lg bg-[#2563EB] px-4 py-3 text-sm font-semibold text-white shadow-sm shadow-blue-500/20">{{ $payment->payment_status === 'pending' ? 'Cobrar pago' : 'Editar pago' }}</a>
                @endcan
                @if ($payment->payment_status === 'paid')
                    <a href="{{ route('payments.receipt', $payment) }}" class="inline-flex items-center justify-center rounded-lg border border-[#2563EB]/30 px-4 py-3 text-sm font-semibold text-[#2563EB]">Descargar recibo PDF</a>
                    <a href="{{ route('payments.receipt.print', $payment) }}" class="inline-flex items-center justify-center rounded-lg border border-[#E2E8F0] px-4 py-3 text-sm font-semibold text-[#0F172A]">Imprimir recibo</a>
                @else
                    <span class="inline-flex items-center rounded-lg border border-[#F59E0B]/20 bg-[#F59E0B]/10 px-4 py-3 text-sm font-semibold text-[#B45309]">Recibo disponible al marcar como pagado</span>
                @endif
                @if ($appointment)
                    <a href="{{ route('appointments.show', $appointment) }}" class="inline-flex items-center justify-center rounded-lg border border-[#E2E8F0] px-4 py-3 text-sm font-semibold text-[#475569]">Ver cita</a>
                @endif
                @if ($patient)
                    <a href="{{ route('patients.show', $patient) }}" class="inline-flex items-center justify-center rounded-lg border border-[#E2E8F0] px-4 py-3 text-sm font-semibold text-[#475569]">Ver paciente</a>
                @endif
            </div>
        </header>

        @if (session('success'))
            <div class="rounded-lg border border-[#10B981]/30 bg-[#10B981]/10 px-4 py-3 text-sm font-semibold text-[#047857]">{{ session('success') }}</div>
        @endif

        <section class="grid gap-5 xl:grid-cols-3">
            <article class="rounded-lg border border-[#E2E8F0] bg-white p-5 shadow-sm xl:col-span-2">
                <h2 class="text-base font-bold text-[#0F172A]">Resumen financiero</h2>
                <dl class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div class="rounded-lg bg-[#F8FAFC] p-4"><dt class="text-xs font-bold uppercase tracking-wide text-[#475569]">Monto</dt><dd class="mt-2 text-lg font-bold text-[#0F172A]">${{ number_format((float) $payment->amount, 2) }}</dd></div>
                    <div class="rounded-lg bg-[#F8FAFC] p-4"><dt class="text-xs font-bold uppercase tracking-wide text-[#475569]">Estado</dt><dd class="mt-2"><span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-bold {{ $statusClasses[$payment->payment_status] ?? 'border-slate-200 bg-slate-100 text-slate-600' }}">{{ $statusLabels[$payment->payment_status] ?? $payment->payment_status }}</span></dd></div>
                    <div class="rounded-lg bg-[#F8FAFC] p-4"><dt class="text-xs font-bold uppercase tracking-wide text-[#475569]">Metodo de pago</dt><dd class="mt-2 text-sm font-semibold text-[#0F172A]">{{ $payment->payment_status === 'pending' ? 'Por definir al cobrar' : ($methodLabels[$payment->payment_method] ?? $payment->payment_method) }}</dd></div>
                    <div class="rounded-lg bg-[#F8FAFC] p-4"><dt class="text-xs font-bold uppercase tracking-wide text-[#475569]">Fecha de pago</dt><dd class="mt-2 text-sm font-semibold text-[#0F172A]">{{ $paymentDate?->format('d/m/Y H:i') ?: 'Sin fecha registrada' }}</dd></div>
                    <div class="rounded-lg bg-[#F8FAFC] p-4"><dt class="text-xs font-bold uppercase tracking-wide text-[#475569]">Fecha de creacion</dt><dd class="mt-2 text-sm font-semibold text-[#0F172A]">{{ $createdAt?->format('d/m/Y H:i') ?: '-' }}</dd></div>
                    <div class="rounded-lg bg-[#F8FAFC] p-4"><dt class="text-xs font-bold uppercase tracking-wide text-[#475569]">Registrado por</dt><dd class="mt-2 text-sm font-semibold text-[#0F172A]">No registrado en esta version</dd></div>
                </dl>
            </article>

            <article class="rounded-lg border border-[#E2E8F0] bg-white p-5 shadow-sm">
                <h2 class="text-base font-bold text-[#0F172A]">Paciente</h2>
                <div class="mt-5 space-y-4 text-sm">
                    <div><p class="text-xs font-bold uppercase tracking-wide text-[#475569]">Nombre completo</p><p class="mt-1 font-semibold text-[#0F172A]">{{ $patient?->full_name ?: '-' }}</p></div>
                    <div><p class="text-xs font-bold uppercase tracking-wide text-[#475569]">Identificacion/cedula</p><p class="mt-1 text-[#0F172A]">{{ $patient?->identification_number ?: '-' }}</p></div>
                    <div><p class="text-xs font-bold uppercase tracking-wide text-[#475569]">Telefono</p><p class="mt-1 text-[#0F172A]">{{ $patient?->phone ?: '-' }}</p></div>
                    <div><p class="text-xs font-bold uppercase tracking-wide text-[#475569]">Correo</p><p class="mt-1 text-[#0F172A]">{{ $patient?->email ?: '-' }}</p></div>
                </div>
            </article>
        </section>

        <section class="grid gap-5 lg:grid-cols-2">
            <article class="rounded-lg border border-[#E2E8F0] bg-white p-5 shadow-sm">
                <h2 class="text-base font-bold text-[#0F172A]">Informacion de la atencion</h2>
                <dl class="mt-5 space-y-4 text-sm">
                    <div class="grid gap-1 sm:grid-cols-3"><dt class="font-semibold text-[#475569]">Servicio</dt><dd class="sm:col-span-2 text-[#0F172A]">{{ $service?->name ?: 'Sin servicio asociado' }}</dd></div>
                    <div class="grid gap-1 sm:grid-cols-3"><dt class="font-semibold text-[#475569]">Medico</dt><dd class="sm:col-span-2 text-[#0F172A]">{{ $doctorName }}</dd></div>
                    <div class="grid gap-1 sm:grid-cols-3"><dt class="font-semibold text-[#475569]">Cita relacionada</dt><dd class="sm:col-span-2 text-[#0F172A]">{{ $appointment ? '#'.$appointment->id : 'Sin cita asociada' }}</dd></div>
                    <div class="grid gap-1 sm:grid-cols-3"><dt class="font-semibold text-[#475569]">Fecha de cita</dt><dd class="sm:col-span-2 text-[#0F172A]">{{ $appointment?->appointment_date?->format('d/m/Y') ?: '-' }}</dd></div>
                    <div class="grid gap-1 sm:grid-cols-3"><dt class="font-semibold text-[#475569]">Hora de cita</dt><dd class="sm:col-span-2 text-[#0F172A]">{{ $appointment?->start_time ? substr((string) $appointment->start_time, 0, 5) : '-' }}</dd></div>
                    <div class="grid gap-1 sm:grid-cols-3"><dt class="font-semibold text-[#475569]">Estado de cita</dt><dd class="sm:col-span-2 text-[#0F172A]">{{ $appointment ? ($appointmentStatusLabels[$appointment->status] ?? $appointment->status) : '-' }}</dd></div>
                </dl>
            </article>

            <article class="rounded-lg border border-[#E2E8F0] bg-white p-5 shadow-sm">
                <h2 class="text-base font-bold text-[#0F172A]">Observaciones</h2>
                <div class="mt-5 rounded-lg bg-[#F8FAFC] p-4">
                    <p class="whitespace-pre-line text-sm leading-6 text-[#475569]">{{ $payment->notes ?: 'Sin notas internas registradas.' }}</p>
                </div>
                @if (in_array($payment->payment_status, ['cancelled', 'refunded'], true))
                    <div class="mt-4 rounded-lg border border-[#EF4444]/20 bg-[#EF4444]/10 p-4 text-sm font-semibold text-[#B91C1C]">Revise las notas internas para el motivo de cancelacion o reembolso.</div>
                @endif
            </article>
        </section>
    </div>
</x-app-layout>