<x-app-layout>
    <div class="space-y-6">
        <header class="flex flex-col gap-4 rounded-lg border border-[#E2E8F0] bg-white p-5 shadow-sm lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-[#2563EB]">Pacientes</p>
                <h1 class="mt-2 text-2xl font-bold tracking-tight text-[#0F172A] sm:text-3xl">Historial clínico</h1>
                <p class="mt-2 text-sm leading-6 text-[#475569]">Gestión de antecedentes y seguimiento clínico de pacientes</p>
            </div>
            @can('medical_records.create')<a href="{{ route('medical-records.create') }}" class="inline-flex items-center justify-center rounded-lg bg-[#2563EB] px-4 py-3 text-sm font-semibold text-white shadow-sm shadow-blue-500/20 transition hover:bg-blue-700">Nuevo historial</a>@endcan
        </header>

        @if (session('success'))
            <div class="rounded-lg border border-[#10B981]/30 bg-[#10B981]/10 px-4 py-3 text-sm font-semibold text-[#047857]">{{ session('success') }}</div>
        @endif

        <form method="GET" action="{{ route('medical-records.index') }}" class="rounded-lg border border-[#E2E8F0] bg-white p-5 shadow-sm">
            <div class="grid gap-4 md:grid-cols-[1fr_auto_auto] md:items-end">
                <div>
                    <label for="search" class="mb-2 block text-sm font-semibold text-[#0F172A]">Buscar</label>
                    <input id="search" name="search" type="search" value="{{ $search }}" placeholder="Paciente, identificacion, antecedentes o enfermedades" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
                </div>
                <a href="{{ route('medical-records.index') }}" class="inline-flex items-center justify-center rounded-lg border border-[#E2E8F0] px-4 py-2.5 text-sm font-semibold text-[#475569]">Limpiar</a>
                <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-[#0F172A] px-4 py-2.5 text-sm font-semibold text-white">Buscar</button>
            </div>
        </form>

        <section class="overflow-hidden rounded-lg border border-[#E2E8F0] bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-[#E2E8F0]">
                    <thead class="bg-[#F8FAFC]">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-[#475569]">Paciente</th>
                            <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-[#475569]">Identificación</th>
                            <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-[#475569]">Antecedentes personales</th>
                            <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-[#475569]">Enfermedades crónicas</th>
                            <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-[#475569]">Medicamentos actuales</th>
                            <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-[#475569]">Última actualización</th>
                            <th class="px-5 py-3 text-right text-xs font-bold uppercase tracking-wide text-[#475569]">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E2E8F0] bg-white">
                        @forelse ($medicalRecords as $record)
                            <tr class="hover:bg-[#F8FAFC]">
                                <td class="px-5 py-4 text-sm font-semibold text-[#0F172A]">{{ $record->patient?->full_name }}</td>
                                <td class="px-5 py-4 text-sm text-[#475569]">{{ $record->patient?->identification_number ?: 'Sin identificacion' }}</td>
                                <td class="max-w-xs px-5 py-4 text-sm text-[#475569]">{{ str($record->personal_history ?: 'Sin registrar')->limit(70) }}</td>
                                <td class="max-w-xs px-5 py-4 text-sm text-[#475569]">{{ str($record->chronic_diseases ?: 'Sin registrar')->limit(70) }}</td>
                                <td class="max-w-xs px-5 py-4 text-sm text-[#475569]">{{ str($record->current_medications ?: 'Sin registrar')->limit(70) }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-[#475569]">{{ $record->updated_at?->format('d/m/Y H:i') }}</td>
                                <td class="px-5 py-4">
                                    <div class="flex justify-end gap-2">
                                        <a href="{{ route('medical-records.show', $record) }}" class="rounded-lg border border-[#E2E8F0] px-3 py-2 text-xs font-semibold text-[#2563EB]">Ver</a>
                                        @can('medical_records.update')<a href="{{ route('medical-records.edit', $record) }}" class="rounded-lg border border-[#E2E8F0] px-3 py-2 text-xs font-semibold text-[#475569]">Editar</a>@endcan
                                        @can('medical_records.delete')<form method="POST" action="{{ route('medical-records.destroy', $record) }}" onsubmit="return confirm('¿Eliminar este historial clínico?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="rounded-lg border border-[#EF4444]/30 px-3 py-2 text-xs font-semibold text-[#EF4444]">Eliminar</button>
                                        </form>@endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-10 text-center text-sm text-[#475569]">No hay historiales clínicos registrados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-[#E2E8F0] px-5 py-4">{{ $medicalRecords->links() }}</div>
        </section>
    </div>
</x-app-layout>
