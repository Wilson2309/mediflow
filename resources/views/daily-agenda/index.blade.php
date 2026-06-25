@php
    $appointmentStatusLabels = [
        'scheduled' => 'Programada',
        'confirmed' => 'Lista para atender',
        'completed' => 'Atendida',
        'cancelled' => 'Cancelada',
        'no_show' => 'No asistio',
    ];
    $appointmentStatusClasses = [
        'scheduled' => 'border-[#2563EB]/20 bg-[#2563EB]/10 text-[#2563EB]',
        'confirmed' => 'border-[#10B981]/20 bg-[#10B981]/10 text-[#047857]',
        'completed' => 'border-slate-200 bg-slate-100 text-[#475569]',
        'cancelled' => 'border-[#EF4444]/20 bg-[#EF4444]/10 text-[#B91C1C]',
        'no_show' => 'border-[#F59E0B]/20 bg-[#F59E0B]/10 text-[#B45309]',
    ];
    $paymentStatusLabels = [
        'pending' => 'Pendiente de pago',
        'paid' => 'Pagado',
        'cancelled' => 'Pago cancelado',
        'refunded' => 'Reembolsado',
    ];
    $paymentStatusClasses = [
        'pending' => 'border-[#F59E0B]/20 bg-[#F59E0B]/10 text-[#B45309]',
        'paid' => 'border-[#10B981]/20 bg-[#10B981]/10 text-[#047857]',
        'cancelled' => 'border-[#EF4444]/20 bg-[#EF4444]/10 text-[#B91C1C]',
        'refunded' => 'border-violet-200 bg-violet-50 text-violet-700',
        'without_payment' => 'border-slate-200 bg-slate-100 text-[#475569]',
    ];
    $quickFilters = [
        ['label' => 'Hoy', 'query' => ['date' => today()->toDateString(), 'status' => null, 'payment_status' => null]],
        ['label' => 'Pendientes de pago', 'query' => ['payment_status' => 'pending']],
        ['label' => 'Pagadas', 'query' => ['payment_status' => 'paid']],
        ['label' => 'Atendidas', 'query' => ['status' => 'completed']],
    ];
    $summaryCards = [
        ['label' => 'Total de citas', 'value' => $summary['total'] ?? 0, 'class' => 'text-[#0F172A]'],
        ['label' => 'Pendientes de pago', 'value' => $summary['pending_payments'] ?? 0, 'class' => 'text-[#B45309]'],
        ['label' => 'Pagadas / listas', 'value' => $summary['paid'] ?? 0, 'class' => 'text-[#047857]'],
        ['label' => 'Atendidas', 'value' => $summary['completed'] ?? 0, 'class' => 'text-[#475569]'],
        ['label' => 'Canceladas / no asistio', 'value' => $summary['cancelled_or_no_show'] ?? 0, 'class' => 'text-[#B91C1C]'],
    ];
@endphp

<x-app-layout>
    <div class="space-y-6">
        <section class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-[#2563EB]">Operacion diaria</p>
                <h1 class="mt-2 text-2xl font-bold tracking-tight text-[#0F172A] sm:text-3xl">Agenda del dia</h1>
                <p class="mt-2 text-sm leading-6 text-[#475569]">{{ $isDoctorView ? 'Citas asignadas a tu atencion medica.' : 'Vista rapida de recepcion, caja y atencion medica del dia.' }}</p>
            </div>
            <div class="flex flex-col gap-3 sm:flex-row">
                <a href="{{ route('daily-agenda.index') }}" class="inline-flex items-center justify-center rounded-lg border border-[#E2E8F0] bg-white px-4 py-3 text-sm font-semibold text-[#475569] shadow-sm">{{ now()->format('d/m/Y') }}</a>
                @can('appointments.create')
                    <a href="{{ route('appointments.create') }}" class="inline-flex items-center justify-center rounded-lg bg-[#2563EB] px-4 py-3 text-sm font-semibold text-white shadow-sm shadow-blue-500/20 transition hover:bg-blue-700">Nueva cita</a>
                @endcan
            </div>
        </section>

        @if (session('success'))
            <div class="rounded-lg border border-[#10B981]/20 bg-[#10B981]/10 px-4 py-3 text-sm font-semibold text-[#047857]">{{ session('success') }}</div>
        @endif

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
            @foreach ($summaryCards as $card)
                <article class="rounded-lg border border-[#E2E8F0] bg-white p-5 shadow-sm">
                    <p class="text-sm font-semibold text-[#475569]">{{ $card['label'] }}</p>
                    <p class="mt-3 text-2xl font-bold {{ $card['class'] }}">{{ number_format($card['value']) }}</p>
                </article>
            @endforeach
            @can('payments.view')
                <article class="rounded-lg border border-[#E2E8F0] bg-white p-5 shadow-sm">
                    <p class="text-sm font-semibold text-[#475569]">Ingresos del dia</p>
                    <p class="mt-3 text-2xl font-bold text-[#10B981]">${{ number_format((float) ($summary['income'] ?? 0), 2) }}</p>
                </article>
            @endcan
        </section>

        <section class="rounded-lg border border-[#E2E8F0] bg-white shadow-sm">
            <form method="GET" action="{{ route('daily-agenda.index') }}" class="grid gap-4 border-b border-[#E2E8F0] p-5 xl:grid-cols-[170px_1fr_210px_190px_190px_auto]">
                <div>
                    <label for="date" class="mb-2 block text-sm font-semibold text-[#0F172A]">Fecha</label>
                    <input id="date" name="date" type="date" value="{{ $selectedDate }}" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
                </div>
                <div>
                    <label for="search" class="mb-2 block text-sm font-semibold text-[#0F172A]">Buscar paciente</label>
                    <input id="search" name="search" type="search" value="{{ $search }}" placeholder="Nombre, identificacion, medico o servicio" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
                </div>
                @unless ($isDoctorView)
                    <div>
                        <label for="doctor_id" class="mb-2 block text-sm font-semibold text-[#0F172A]">Medico</label>
                        <select id="doctor_id" name="doctor_id" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
                            <option value="">Todos</option>
                            @foreach ($doctors as $doctor)
                                <option value="{{ $doctor->id }}" @selected((string) $doctorId === (string) $doctor->id)>{{ $doctor->user?->name ?? 'Usuario no asignado' }}</option>
                            @endforeach
                        </select>
                    </div>
                @endunless
                <div>
                    <label for="status" class="mb-2 block text-sm font-semibold text-[#0F172A]">Estado cita</label>
                    <select id="status" name="status" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
                        <option value="">Todos</option>
                        @foreach ($appointmentStatusLabels as $value => $label)
                            <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="payment_status" class="mb-2 block text-sm font-semibold text-[#0F172A]">Estado pago</label>
                    <select id="payment_status" name="payment_status" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
                        <option value="">Todos</option>
                        @foreach ($paymentStatusLabels as $value => $label)
                            <option value="{{ $value }}" @selected($paymentStatus === $value)>{{ $label }}</option>
                        @endforeach
                        <option value="without_payment" @selected($paymentStatus === 'without_payment')>Sin pago</option>
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="inline-flex h-10 items-center justify-center rounded-lg bg-[#0F172A] px-4 text-sm font-semibold text-white">Filtrar</button>
                    <a href="{{ route('daily-agenda.index') }}" class="inline-flex h-10 items-center justify-center rounded-lg border border-[#E2E8F0] px-4 text-sm font-semibold text-[#475569]">Limpiar</a>
                </div>
            </form>

            <div class="flex flex-wrap gap-2 border-b border-[#E2E8F0] px-5 py-4">
                @foreach ($quickFilters as $filter)
                    @php
                        $query = array_filter(array_merge(request()->except('page'), $filter['query']), fn ($value) => filled($value));
                    @endphp
                    <a href="{{ route('daily-agenda.index', $query) }}" class="inline-flex items-center rounded-full border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-1.5 text-xs font-bold text-[#475569] transition hover:border-[#2563EB] hover:text-[#2563EB]">{{ $filter['label'] }}</a>
                @endforeach
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-[#E2E8F0] text-left">
                    <thead class="bg-[#F8FAFC]">
                        <tr>
                            <th class="px-5 py-3 text-xs font-bold uppercase tracking-wide text-[#475569]">Hora</th>
                            <th class="px-5 py-3 text-xs font-bold uppercase tracking-wide text-[#475569]">Paciente</th>
                            <th class="px-5 py-3 text-xs font-bold uppercase tracking-wide text-[#475569]">Medico</th>
                            <th class="px-5 py-3 text-xs font-bold uppercase tracking-wide text-[#475569]">Servicio</th>
                            <th class="px-5 py-3 text-xs font-bold uppercase tracking-wide text-[#475569]">Estado de pago</th>
                            <th class="px-5 py-3 text-xs font-bold uppercase tracking-wide text-[#475569]">Estado de cita</th>
                            <th class="px-5 py-3 text-right text-xs font-bold uppercase tracking-wide text-[#475569]">Accion principal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E2E8F0] bg-white">
                        @forelse ($appointments as $appointment)
                            @php
                                $payment = $appointment->payment;
                                $paymentKey = $payment?->payment_status ?? 'without_payment';
                                $hasPaidPayment = $payment?->payment_status === 'paid';
                                $canStartConsultation = auth()->user()?->can('consultations.create')
                                    && ! $appointment->consultation
                                    && ! in_array($appointment->status, ['cancelled', 'no_show', 'completed'], true)
                                    && ($hasPaidPayment || auth()->user()?->hasRole('administrador'));
                                $canSeeConsultation = $appointment->consultation && auth()->user()?->can('consultations.view');
                                $canCloseAppointment = auth()->user()?->can('appointments.update') && ! in_array($appointment->status, ['cancelled', 'no_show', 'completed'], true);
                            @endphp
                            <tr class="transition hover:bg-[#F8FAFC]">
                                <td class="whitespace-nowrap px-5 py-4 text-sm font-bold text-[#0F172A]">
                                    {{ substr((string) $appointment->start_time, 0, 5) }}{{ $appointment->end_time ? ' - '.substr((string) $appointment->end_time, 0, 5) : '' }}
                                </td>
                                <td class="px-5 py-4">
                                    <p class="whitespace-nowrap text-sm font-semibold text-[#0F172A]">{{ $appointment->patient?->full_name }}</p>
                                    <p class="mt-1 whitespace-nowrap text-xs text-[#475569]">{{ $appointment->patient?->identification_number ?: 'Sin identificacion' }}</p>
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-[#475569]">{{ $appointment->doctor?->user?->name ?: 'Sin medico' }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-[#475569]">{{ $appointment->service?->name ?? 'Sin servicio' }}</td>
                                <td class="whitespace-nowrap px-5 py-4">
                                    <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-bold {{ $paymentStatusClasses[$paymentKey] ?? 'border-slate-200 bg-slate-100 text-[#475569]' }}">{{ $payment ? ($paymentStatusLabels[$payment->payment_status] ?? $payment->payment_status) : 'Sin pago' }}</span>
                                    @if ($payment && auth()->user()?->can('payments.view'))
                                        <p class="mt-2 text-xs font-bold text-[#0F172A]">${{ number_format((float) $payment->amount, 2) }}</p>
                                    @endif
                                    @if ($isDoctorView && ! $hasPaidPayment)
                                        <p class="mt-2 text-xs font-semibold text-[#B45309]">Pendiente de pago</p>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-5 py-4">
                                    <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-bold {{ $appointmentStatusClasses[$appointment->status] ?? 'border-slate-200 bg-slate-100 text-[#475569]' }}">{{ $appointmentStatusLabels[$appointment->status] ?? $appointment->status }}</span>
                                </td>
                                <td class="px-5 py-4">
                                    <div class="flex flex-wrap items-center justify-end gap-2">
                                        <a href="{{ route('appointments.show', $appointment) }}" class="rounded-lg border border-[#E2E8F0] px-3 py-2 text-xs font-semibold text-[#2563EB]">Ver cita</a>
                                        @can('patients.view')
                                            <a href="{{ route('patients.show', $appointment->patient) }}" class="rounded-lg border border-[#E2E8F0] px-3 py-2 text-xs font-semibold text-[#0F172A]">Ver paciente</a>
                                        @endcan
                                        @if ($payment && $payment->payment_status === 'pending' && auth()->user()?->can('payments.update'))
                                            <a href="{{ route('payments.edit', $payment) }}" class="rounded-lg bg-[#2563EB] px-3 py-2 text-xs font-semibold text-white">Cobrar</a>
                                        @elseif ($payment && auth()->user()?->can('payments.view'))
                                            <a href="{{ route('payments.show', $payment) }}" class="rounded-lg border border-[#E2E8F0] px-3 py-2 text-xs font-semibold text-[#2563EB]">Ver pago</a>
                                        @endif
                                        @if ($canStartConsultation)
                                            <a href="{{ route('consultations.create', ['appointment_id' => $appointment->id]) }}" class="rounded-lg bg-[#10B981] px-3 py-2 text-xs font-semibold text-white">Iniciar consulta</a>
                                        @elseif ($canSeeConsultation)
                                            <a href="{{ route('consultations.show', $appointment->consultation) }}" class="rounded-lg border border-[#10B981]/30 px-3 py-2 text-xs font-semibold text-[#047857]">Ver consulta</a>
                                        @endif
                                        @can('appointments.update')
                                            <a href="{{ route('appointments.edit', $appointment) }}" class="rounded-lg border border-[#E2E8F0] px-3 py-2 text-xs font-semibold text-[#475569]">Reprogramar</a>
                                        @endcan
                                        @if ($canCloseAppointment)
                                            <form method="POST" action="{{ route('daily-agenda.appointments.no-show', $appointment) }}" onsubmit="return confirm('Marcar esta cita como no asistio?');">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="rounded-lg border border-[#F59E0B]/30 px-3 py-2 text-xs font-semibold text-[#B45309]">No asistio</button>
                                            </form>
                                            <form method="POST" action="{{ route('daily-agenda.appointments.cancel', $appointment) }}" onsubmit="return confirm('Cancelar esta cita?');">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="rounded-lg border border-[#EF4444]/30 px-3 py-2 text-xs font-semibold text-[#EF4444]">Cancelar</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-12 text-center">
                                    <p class="text-sm font-semibold text-[#0F172A]">No hay citas para la fecha seleccionada.</p>
                                    <p class="mt-1 text-sm text-[#475569]">Ajusta filtros o registra una nueva cita si corresponde.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($appointments->hasPages())
                <div class="border-t border-[#E2E8F0] px-5 py-4">{{ $appointments->links() }}</div>
            @endif
        </section>
    </div>
</x-app-layout>
