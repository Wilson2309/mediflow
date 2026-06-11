<x-app-layout>
    @php
        $statusLabels = [
            'scheduled' => 'Programada',
            'confirmed' => 'Confirmada',
            'completed' => 'Completada',
            'cancelled' => 'Cancelada',
            'no_show' => 'No asistió',
        ];
        $statusClasses = [
            'scheduled' => 'border-[#2563EB]/20 bg-[#2563EB]/10 text-[#2563EB]',
            'confirmed' => 'border-[#10B981]/20 bg-[#10B981]/10 text-[#10B981]',
            'completed' => 'border-slate-200 bg-slate-100 text-[#475569]',
            'cancelled' => 'border-[#EF4444]/20 bg-[#EF4444]/10 text-[#EF4444]',
            'no_show' => 'border-[#F59E0B]/20 bg-[#F59E0B]/10 text-[#F59E0B]',
        ];
    @endphp

    <div class="space-y-6">
        <section class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-[#2563EB]">Agenda médica</p>
                <h1 class="mt-2 text-2xl font-bold tracking-tight text-[#0F172A] sm:text-3xl">Citas médicas</h1>
                <p class="mt-2 text-sm leading-6 text-[#475569]">Gestión de agenda y programación de citas del consultorio</p>
            </div>
            <a href="{{ route('appointments.create') }}" class="inline-flex items-center justify-center rounded-lg bg-[#2563EB] px-4 py-3 text-sm font-semibold text-white shadow-sm shadow-blue-500/20 transition hover:bg-blue-700">Nueva cita</a>
        </section>

        @if (session('success'))
            <div class="rounded-lg border border-[#10B981]/20 bg-[#10B981]/10 px-4 py-3 text-sm font-semibold text-[#047857]">{{ session('success') }}</div>
        @endif

        <section class="rounded-lg border border-[#E2E8F0] bg-white shadow-sm">
            <form method="GET" action="{{ route('appointments.index') }}" class="grid gap-4 border-b border-[#E2E8F0] p-5 xl:grid-cols-[1fr_190px_230px_170px_auto]">
                <div>
                    <label for="search" class="mb-2 block text-sm font-semibold text-[#0F172A]">Buscar cita</label>
                    <input id="search" name="search" type="search" value="{{ $search }}" placeholder="Paciente, médico o motivo" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
                </div>
                <div>
                    <label for="status" class="mb-2 block text-sm font-semibold text-[#0F172A]">Estado</label>
                    <select id="status" name="status" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
                        <option value="">Todos</option>
                        @foreach ($statusLabels as $value => $label)
                            <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="doctor_id" class="mb-2 block text-sm font-semibold text-[#0F172A]">Médico</label>
                    <select id="doctor_id" name="doctor_id" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
                        <option value="">Todos</option>
                        @foreach ($doctors as $doctor)
                            <option value="{{ $doctor->id }}" @selected((string) $doctorId === (string) $doctor->id)>{{ $doctor->user?->name ?? 'Usuario no asignado' }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="date" class="mb-2 block text-sm font-semibold text-[#0F172A]">Fecha</label>
                    <input id="date" name="date" type="date" value="{{ $date }}" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="inline-flex h-10 items-center justify-center rounded-lg bg-[#0F172A] px-4 text-sm font-semibold text-white">Filtrar</button>
                    <a href="{{ route('appointments.index') }}" class="inline-flex h-10 items-center justify-center rounded-lg border border-[#E2E8F0] px-4 text-sm font-semibold text-[#475569]">Limpiar</a>
                </div>
            </form>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-[#E2E8F0] text-left">
                    <thead class="bg-[#F8FAFC]">
                        <tr>
                            <th class="px-5 py-3 text-xs font-bold uppercase tracking-wide text-[#475569]">Fecha</th>
                            <th class="px-5 py-3 text-xs font-bold uppercase tracking-wide text-[#475569]">Hora</th>
                            <th class="px-5 py-3 text-xs font-bold uppercase tracking-wide text-[#475569]">Paciente</th>
                            <th class="px-5 py-3 text-xs font-bold uppercase tracking-wide text-[#475569]">Médico</th>
                            <th class="px-5 py-3 text-xs font-bold uppercase tracking-wide text-[#475569]">Servicio</th>
                            <th class="px-5 py-3 text-xs font-bold uppercase tracking-wide text-[#475569]">Estado</th>
                            <th class="px-5 py-3 text-xs font-bold uppercase tracking-wide text-[#475569]">Motivo</th>
                            <th class="px-5 py-3 text-right text-xs font-bold uppercase tracking-wide text-[#475569]">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E2E8F0] bg-white">
                        @forelse ($appointments as $appointment)
                            <tr class="transition hover:bg-[#F8FAFC]">
                                <td class="whitespace-nowrap px-5 py-4 text-sm font-semibold text-[#0F172A]">{{ $appointment->appointment_date?->format('d/m/Y') }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-[#475569]">{{ substr((string) $appointment->start_time, 0, 5) }}{{ $appointment->end_time ? ' - '.substr((string) $appointment->end_time, 0, 5) : '' }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-[#475569]">{{ $appointment->patient?->full_name }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-[#475569]">{{ $appointment->doctor?->user?->name }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-[#475569]">{{ $appointment->service?->name ?? 'Sin servicio' }}</td>
                                <td class="whitespace-nowrap px-5 py-4"><span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-bold {{ $statusClasses[$appointment->status] ?? 'border-slate-200 bg-slate-100 text-slate-600' }}">{{ $statusLabels[$appointment->status] ?? $appointment->status }}</span></td>
                                <td class="max-w-xs truncate px-5 py-4 text-sm text-[#475569]">{{ $appointment->reason ?: 'Sin motivo' }}</td>
                                <td class="whitespace-nowrap px-5 py-4">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="{{ route('appointments.show', $appointment) }}" class="rounded-lg border border-[#E2E8F0] px-3 py-2 text-xs font-semibold text-[#2563EB]">Ver</a>
                                        <a href="{{ route('appointments.edit', $appointment) }}" class="rounded-lg border border-[#E2E8F0] px-3 py-2 text-xs font-semibold text-[#0F172A]">Editar</a>
                                        <form method="POST" action="{{ route('appointments.destroy', $appointment) }}" onsubmit="return confirm('Eliminar esta cita?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="rounded-lg border border-[#EF4444]/20 px-3 py-2 text-xs font-semibold text-[#EF4444]">Eliminar</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-5 py-12 text-center text-sm text-[#475569]">No hay citas registradas.</td></tr>
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
