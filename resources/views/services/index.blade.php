<x-app-layout>
    <div class="space-y-6">
        <section class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-[#2563EB]">Catálogo clínico</p>
                <h1 class="mt-2 text-2xl font-bold tracking-tight text-[#0F172A] sm:text-3xl">Servicios médicos</h1>
                <p class="mt-2 text-sm leading-6 text-[#475569]">Administra los servicios disponibles, sus precios y duración estimada.</p>
            </div>
            @can('services.create')<a href="{{ route('services.create') }}" class="inline-flex items-center justify-center rounded-lg bg-[#2563EB] px-4 py-3 text-sm font-semibold text-white shadow-sm shadow-blue-500/20 transition hover:bg-blue-700">Nuevo servicio</a>@endcan
        </section>

        @if (session('success'))
            <div class="rounded-lg border border-[#10B981]/20 bg-[#10B981]/10 px-4 py-3 text-sm font-semibold text-[#047857]">{{ session('success') }}</div>
        @endif

        <section class="rounded-lg border border-[#E2E8F0] bg-white shadow-sm">
            <form method="GET" action="{{ route('services.index') }}" class="grid gap-4 border-b border-[#E2E8F0] p-5 lg:grid-cols-[1fr_220px_auto]">
                <div>
                    <label for="search" class="mb-2 block text-sm font-semibold text-[#0F172A]">Buscar servicio</label>
                    <input id="search" name="search" type="search" value="{{ $search }}" placeholder="Nombre o descripción" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm text-[#0F172A] placeholder:text-slate-400 focus:border-[#2563EB] focus:ring-[#2563EB]">
                </div>
                <div>
                    <label for="status" class="mb-2 block text-sm font-semibold text-[#0F172A]">Estado</label>
                    <select id="status" name="status" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm text-[#0F172A] focus:border-[#2563EB] focus:ring-[#2563EB]">
                        <option value="">Todos</option>
                        <option value="active" @selected($status === 'active')>Activos</option>
                        <option value="inactive" @selected($status === 'inactive')>Inactivos</option>
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="inline-flex h-10 items-center justify-center rounded-lg bg-[#0F172A] px-4 text-sm font-semibold text-white transition hover:bg-slate-800">Filtrar</button>
                    <a href="{{ route('services.index') }}" class="inline-flex h-10 items-center justify-center rounded-lg border border-[#E2E8F0] px-4 text-sm font-semibold text-[#475569] transition hover:border-[#2563EB] hover:text-[#2563EB]">Limpiar</a>
                </div>
            </form>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-[#E2E8F0] text-left">
                    <thead class="bg-[#F8FAFC]"><tr><th class="px-5 py-3 text-xs font-bold uppercase tracking-wide text-[#475569]">Nombre</th><th class="px-5 py-3 text-xs font-bold uppercase tracking-wide text-[#475569]">Precio</th><th class="px-5 py-3 text-xs font-bold uppercase tracking-wide text-[#475569]">Duración</th><th class="px-5 py-3 text-xs font-bold uppercase tracking-wide text-[#475569]">Estado</th><th class="px-5 py-3 text-xs font-bold uppercase tracking-wide text-[#475569]">Fecha de creación</th><th class="px-5 py-3 text-right text-xs font-bold uppercase tracking-wide text-[#475569]">Acciones</th></tr></thead>
                    <tbody class="divide-y divide-[#E2E8F0] bg-white">
                        @forelse ($services as $service)
                            <tr class="transition hover:bg-[#F8FAFC]">
                                <td class="px-5 py-4"><p class="text-sm font-semibold text-[#0F172A]">{{ $service->name }}</p><p class="mt-1 max-w-md truncate text-xs text-[#475569]">{{ $service->description ?: 'Sin descripción' }}</p></td>
                                <td class="whitespace-nowrap px-5 py-4 text-sm font-semibold text-[#0F172A]">${{ number_format((float) $service->price, 2) }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-[#475569]">{{ $service->duration_minutes }} min</td>
                                <td class="whitespace-nowrap px-5 py-4">@if ($service->status === 'active')<span class="inline-flex rounded-full border border-[#10B981]/20 bg-[#10B981]/10 px-2.5 py-1 text-xs font-bold text-[#10B981]">Activo</span>@else<span class="inline-flex rounded-full border border-slate-200 bg-slate-100 px-2.5 py-1 text-xs font-bold text-[#475569]">Inactivo</span>@endif</td>
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-[#475569]">{{ $service->created_at?->format('d/m/Y') }}</td>
                                <td class="whitespace-nowrap px-5 py-4"><div class="flex items-center justify-end gap-2"><a href="{{ route('services.show', $service) }}" class="rounded-lg border border-[#E2E8F0] px-3 py-2 text-xs font-semibold text-[#2563EB] transition hover:border-[#2563EB] hover:bg-[#2563EB]/5">Ver</a>@can('services.update')<a href="{{ route('services.edit', $service) }}" class="rounded-lg border border-[#E2E8F0] px-3 py-2 text-xs font-semibold text-[#0F172A] transition hover:border-[#0F172A] hover:bg-slate-50">Editar</a>@endcan @can('services.delete')<form method="POST" action="{{ route('services.destroy', $service) }}" onsubmit="return confirm('¿Eliminar este servicio? Esta acción no se puede deshacer.');">@csrf @method('DELETE')<button type="submit" class="rounded-lg border border-[#EF4444]/20 px-3 py-2 text-xs font-semibold text-[#EF4444] transition hover:bg-[#EF4444]/5">Eliminar</button></form>@endcan</div></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-5 py-12 text-center"><p class="text-sm font-semibold text-[#0F172A]">No hay servicios registrados.</p><p class="mt-1 text-sm text-[#475569]">Crea el primer servicio para organizar la atención clínica.</p></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($services->hasPages())<div class="border-t border-[#E2E8F0] px-5 py-4">{{ $services->links() }}</div>@endif
        </section>
    </div>
</x-app-layout>
