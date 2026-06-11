<x-app-layout>
    <div class="space-y-6">
        <header class="flex flex-col gap-4 rounded-lg border border-[#E2E8F0] bg-white p-5 shadow-sm lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-[#2563EB]">Indicaciones médicas</p>
                <h1 class="mt-2 text-2xl font-bold tracking-tight text-[#0F172A] sm:text-3xl">Recetas médicas</h1>
                <p class="mt-2 text-sm leading-6 text-[#475569]">Gestión de recetas e indicaciones médicas generadas en consulta</p>
            </div>
            @can('prescriptions.create')<a href="{{ route('prescriptions.create') }}" class="inline-flex items-center justify-center rounded-lg bg-[#2563EB] px-4 py-3 text-sm font-semibold text-white shadow-sm shadow-blue-500/20 transition hover:bg-blue-700">Nueva receta</a>@endcan
        </header>

        @if (session('success'))
            <div class="rounded-lg border border-[#10B981]/30 bg-[#10B981]/10 px-4 py-3 text-sm font-semibold text-[#047857]">{{ session('success') }}</div>
        @endif

        <form method="GET" action="{{ route('prescriptions.index') }}" class="rounded-lg border border-[#E2E8F0] bg-white p-5 shadow-sm">
            <div class="grid gap-4 md:grid-cols-5">
                <div class="md:col-span-2">
                    <label for="search" class="mb-2 block text-sm font-semibold text-[#0F172A]">Buscar</label>
                    <input id="search" name="search" type="search" value="{{ $search }}" placeholder="Paciente, médico, medicamento o instrucciones" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
                </div>
                <div>
                    <label for="doctor_id" class="mb-2 block text-sm font-semibold text-[#0F172A]">Médico</label>
                    <select id="doctor_id" name="doctor_id" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
                        <option value="">Todos</option>
                        @foreach ($doctors as $doctor)
                            <option value="{{ $doctor->id }}" @selected((string) $doctorId === (string) $doctor->id)>{{ $doctor->user?->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="status" class="mb-2 block text-sm font-semibold text-[#0F172A]">Estado</label>
                    <select id="status" name="status" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
                        <option value="">Todos</option>
                        <option value="active" @selected($status === 'active')>Activas</option>
                        <option value="cancelled" @selected($status === 'cancelled')>Canceladas</option>
                    </select>
                </div>
                <div>
                    <label for="date" class="mb-2 block text-sm font-semibold text-[#0F172A]">Fecha</label>
                    <input id="date" name="date" type="date" value="{{ $date }}" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
                </div>
            </div>
            <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:justify-end">
                <a href="{{ route('prescriptions.index') }}" class="inline-flex items-center justify-center rounded-lg border border-[#E2E8F0] px-4 py-2.5 text-sm font-semibold text-[#475569]">Limpiar</a>
                <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-[#0F172A] px-4 py-2.5 text-sm font-semibold text-white">Filtrar</button>
            </div>
        </form>

        <section class="overflow-hidden rounded-lg border border-[#E2E8F0] bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-[#E2E8F0]">
                    <thead class="bg-[#F8FAFC]">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-[#475569]">Fecha</th>
                            <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-[#475569]">Paciente</th>
                            <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-[#475569]">Médico</th>
                            <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-[#475569]">Consulta asociada</th>
                            <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-[#475569]">Medicamentos</th>
                            <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-[#475569]">Estado</th>
                            <th class="px-5 py-3 text-right text-xs font-bold uppercase tracking-wide text-[#475569]">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E2E8F0] bg-white">
                        @forelse ($prescriptions as $prescription)
                            <tr class="hover:bg-[#F8FAFC]">
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-[#0F172A]">{{ $prescription->prescription_date?->format('d/m/Y') }}</td>
                                <td class="px-5 py-4 text-sm font-semibold text-[#0F172A]">{{ $prescription->patient?->full_name }}</td>
                                <td class="px-5 py-4 text-sm text-[#475569]">{{ $prescription->doctor?->user?->name }}</td>
                                <td class="px-5 py-4 text-sm text-[#475569]">{{ $prescription->consultation ? $prescription->consultation->consultation_date?->format('d/m/Y') : 'Sin consulta' }}</td>
                                <td class="max-w-xs px-5 py-4 text-sm text-[#475569]">{{ Str::limit($prescription->items->pluck('medication_name')->join(', '), 60) }}</td>
                                <td class="px-5 py-4">
                                    <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-bold {{ $prescription->status === 'active' ? 'border-[#10B981]/20 bg-[#10B981]/10 text-[#10B981]' : 'border-[#EF4444]/20 bg-[#EF4444]/10 text-[#EF4444]' }}">
                                        {{ $prescription->status === 'active' ? 'Activa' : 'Cancelada' }}
                                    </span>
                                </td>
                                <td class="px-5 py-4">
                                    <div class="flex justify-end gap-2">
                                        <a href="{{ route('prescriptions.show', $prescription) }}" class="rounded-lg border border-[#E2E8F0] px-3 py-2 text-xs font-semibold text-[#2563EB]">Ver</a>
                                        @can('prescriptions.update')<a href="{{ route('prescriptions.edit', $prescription) }}" class="rounded-lg border border-[#E2E8F0] px-3 py-2 text-xs font-semibold text-[#475569]">Editar</a>@endcan
                                        @can('prescriptions.delete')<form method="POST" action="{{ route('prescriptions.destroy', $prescription) }}" onsubmit="return confirm('¿Eliminar esta receta?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="rounded-lg border border-[#EF4444]/30 px-3 py-2 text-xs font-semibold text-[#EF4444]">Eliminar</button>
                                        </form>@endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-5 py-10 text-center text-sm text-[#475569]">No hay recetas registradas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-[#E2E8F0] px-5 py-4">{{ $prescriptions->links() }}</div>
        </section>
    </div>
</x-app-layout>
