<x-app-layout>
    <div class="space-y-6">
        <section class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-[#2563EB]">Modulo clinico</p>
                <h1 class="mt-2 text-2xl font-bold tracking-tight text-[#0F172A] sm:text-3xl">Pacientes</h1>
                <p class="mt-2 text-sm leading-6 text-[#475569]">Gestion de pacientes registrados en el consultorio</p>
            </div>

            <a href="{{ route('patients.create') }}" class="inline-flex items-center justify-center rounded-lg bg-[#2563EB] px-4 py-3 text-sm font-semibold text-white shadow-sm shadow-blue-500/20 transition hover:bg-blue-700">
                Nuevo paciente
            </a>
        </section>

        @if (session('success'))
            <div class="rounded-lg border border-[#10B981]/20 bg-[#10B981]/10 px-4 py-3 text-sm font-semibold text-[#047857]">
                {{ session('success') }}
            </div>
        @endif

        <section class="rounded-lg border border-[#E2E8F0] bg-white shadow-sm">
            <form method="GET" action="{{ route('patients.index') }}" class="grid gap-4 border-b border-[#E2E8F0] p-5 lg:grid-cols-[1fr_220px_auto]">
                <div>
                    <label for="search" class="mb-2 block text-sm font-semibold text-[#0F172A]">Buscar paciente</label>
                    <input
                        id="search"
                        name="search"
                        type="search"
                        value="{{ $search }}"
                        placeholder="Nombre, identificacion, telefono o email"
                        class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm text-[#0F172A] placeholder:text-slate-400 focus:border-[#2563EB] focus:ring-[#2563EB]"
                    >
                </div>

                <div>
                    <label for="status" class="mb-2 block text-sm font-semibold text-[#0F172A]">Estado</label>
                    <select
                        id="status"
                        name="status"
                        class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm text-[#0F172A] focus:border-[#2563EB] focus:ring-[#2563EB]"
                    >
                        <option value="">Todos</option>
                        <option value="active" @selected($status === 'active')>Activos</option>
                        <option value="inactive" @selected($status === 'inactive')>Inactivos</option>
                    </select>
                </div>

                <div class="flex items-end gap-2">
                    <button type="submit" class="inline-flex h-10 items-center justify-center rounded-lg bg-[#0F172A] px-4 text-sm font-semibold text-white transition hover:bg-slate-800">
                        Filtrar
                    </button>
                    <a href="{{ route('patients.index') }}" class="inline-flex h-10 items-center justify-center rounded-lg border border-[#E2E8F0] px-4 text-sm font-semibold text-[#475569] transition hover:border-[#2563EB] hover:text-[#2563EB]">
                        Limpiar
                    </a>
                </div>
            </form>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-[#E2E8F0] text-left">
                    <thead class="bg-[#F8FAFC]">
                        <tr>
                            <th class="px-5 py-3 text-xs font-bold uppercase tracking-wide text-[#475569]">Nombre completo</th>
                            <th class="px-5 py-3 text-xs font-bold uppercase tracking-wide text-[#475569]">Identificacion</th>
                            <th class="px-5 py-3 text-xs font-bold uppercase tracking-wide text-[#475569]">Telefono</th>
                            <th class="px-5 py-3 text-xs font-bold uppercase tracking-wide text-[#475569]">Email</th>
                            <th class="px-5 py-3 text-xs font-bold uppercase tracking-wide text-[#475569]">Estado</th>
                            <th class="px-5 py-3 text-xs font-bold uppercase tracking-wide text-[#475569]">Fecha de registro</th>
                            <th class="px-5 py-3 text-right text-xs font-bold uppercase tracking-wide text-[#475569]">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E2E8F0] bg-white">
                        @forelse ($patients as $patient)
                            <tr class="transition hover:bg-[#F8FAFC]">
                                <td class="whitespace-nowrap px-5 py-4">
                                    <p class="text-sm font-semibold text-[#0F172A]">{{ $patient->full_name }}</p>
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-[#475569]">
                                    {{ $patient->identification_number ?: 'Sin registrar' }}
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-[#475569]">
                                    {{ $patient->phone ?: 'Sin registrar' }}
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-[#475569]">
                                    {{ $patient->email ?: 'Sin registrar' }}
                                </td>
                                <td class="whitespace-nowrap px-5 py-4">
                                    @if ($patient->status === 'active')
                                        <span class="inline-flex rounded-full border border-[#10B981]/20 bg-[#10B981]/10 px-2.5 py-1 text-xs font-bold text-[#10B981]">Activo</span>
                                    @else
                                        <span class="inline-flex rounded-full border border-slate-200 bg-slate-100 px-2.5 py-1 text-xs font-bold text-[#475569]">Inactivo</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-[#475569]">
                                    {{ $patient->created_at?->format('d/m/Y') }}
                                </td>
                                <td class="whitespace-nowrap px-5 py-4">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="{{ route('patients.show', $patient) }}" class="rounded-lg border border-[#E2E8F0] px-3 py-2 text-xs font-semibold text-[#2563EB] transition hover:border-[#2563EB] hover:bg-[#2563EB]/5">Ver</a>
                                        <a href="{{ route('patients.edit', $patient) }}" class="rounded-lg border border-[#E2E8F0] px-3 py-2 text-xs font-semibold text-[#0F172A] transition hover:border-[#0F172A] hover:bg-slate-50">Editar</a>
                                        <form method="POST" action="{{ route('patients.destroy', $patient) }}" onsubmit="return confirm('¿Eliminar este paciente? Esta accion no se puede deshacer.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="rounded-lg border border-[#EF4444]/20 px-3 py-2 text-xs font-semibold text-[#EF4444] transition hover:bg-[#EF4444]/5">Eliminar</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-12 text-center">
                                    <p class="text-sm font-semibold text-[#0F172A]">No hay pacientes registrados.</p>
                                    <p class="mt-1 text-sm text-[#475569]">Crea el primer paciente para iniciar la gestion clinica.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($patients->hasPages())
                <div class="border-t border-[#E2E8F0] px-5 py-4">
                    {{ $patients->links() }}
                </div>
            @endif
        </section>
    </div>
</x-app-layout>
