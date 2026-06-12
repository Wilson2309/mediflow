@php
    $statusClasses = [
        'pending' => 'border-[#F59E0B]/20 bg-[#F59E0B]/10 text-[#B45309]',
        'contacted' => 'border-[#2563EB]/20 bg-[#2563EB]/10 text-[#2563EB]',
        'converted' => 'border-[#10B981]/20 bg-[#10B981]/10 text-[#047857]',
        'discarded' => 'border-slate-200 bg-slate-100 text-[#475569]',
    ];
@endphp

<x-app-layout>
    <div class="space-y-6">
        <section>
            <p class="text-sm font-semibold uppercase tracking-wide text-[#2563EB]">Oportunidades comerciales</p>
            <h1 class="mt-2 text-2xl font-bold tracking-tight text-[#0F172A] sm:text-3xl">Solicitudes de demo</h1>
            <p class="mt-2 text-sm leading-6 text-[#475569]">Consulta y da seguimiento a las personas interesadas en implementar MediFlow.</p>
        </section>

        @if (session('success'))
            <div class="rounded-lg border border-[#10B981]/20 bg-[#10B981]/10 px-4 py-3 text-sm font-semibold text-[#047857]">{{ session('success') }}</div>
        @endif

        <section class="rounded-lg border border-[#E2E8F0] bg-white shadow-sm">
            <form method="GET" action="{{ route('demo-requests.index') }}" class="grid gap-4 border-b border-[#E2E8F0] p-5 lg:grid-cols-[1fr_210px_240px_auto]">
                <div>
                    <label for="search" class="mb-2 block text-sm font-semibold text-[#0F172A]">Buscar solicitud</label>
                    <input id="search" name="search" type="search" value="{{ $search }}" placeholder="Nombre, correo o teléfono" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
                </div>
                <div>
                    <label for="status" class="mb-2 block text-sm font-semibold text-[#0F172A]">Estado</label>
                    <select id="status" name="status" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
                        <option value="">Todos</option>
                        @foreach (\App\Models\DemoRequest::STATUSES as $value => $label)
                            <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="interest_module" class="mb-2 block text-sm font-semibold text-[#0F172A]">Módulo de interés</label>
                    <select id="interest_module" name="interest_module" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
                        <option value="">Todos</option>
                        @foreach (\App\Models\DemoRequest::INTEREST_MODULES as $value => $label)
                            <option value="{{ $value }}" @selected($interestModule === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="inline-flex h-10 items-center justify-center rounded-lg bg-[#0F172A] px-4 text-sm font-semibold text-white transition hover:bg-slate-800">Filtrar</button>
                    <a href="{{ route('demo-requests.index') }}" class="inline-flex h-10 items-center justify-center rounded-lg border border-[#E2E8F0] px-4 text-sm font-semibold text-[#475569] transition hover:border-[#2563EB] hover:text-[#2563EB]">Limpiar</a>
                </div>
            </form>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-[#E2E8F0] text-left">
                    <thead class="bg-[#F8FAFC]">
                        <tr>
                            <th class="px-5 py-3 text-xs font-bold uppercase tracking-wide text-[#475569]">Contacto</th>
                            <th class="px-5 py-3 text-xs font-bold uppercase tracking-wide text-[#475569]">Consultorio</th>
                            <th class="px-5 py-3 text-xs font-bold uppercase tracking-wide text-[#475569]">Interés</th>
                            <th class="px-5 py-3 text-xs font-bold uppercase tracking-wide text-[#475569]">Estado</th>
                            <th class="px-5 py-3 text-xs font-bold uppercase tracking-wide text-[#475569]">Recibida</th>
                            <th class="px-5 py-3 text-right text-xs font-bold uppercase tracking-wide text-[#475569]">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E2E8F0] bg-white">
                        @forelse ($demoRequests as $demoRequest)
                            <tr class="transition hover:bg-[#F8FAFC]">
                                <td class="px-5 py-4">
                                    <p class="text-sm font-semibold text-[#0F172A]">{{ $demoRequest->full_name }}</p>
                                    <p class="mt-1 text-xs text-[#475569]">{{ $demoRequest->email }}</p>
                                    @if ($demoRequest->phone)<p class="mt-1 text-xs text-[#475569]">{{ $demoRequest->phone }}</p>@endif
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-[#475569]">{{ \App\Models\DemoRequest::CLINIC_TYPES[$demoRequest->clinic_type] ?? 'No especificado' }}</td>
                                <td class="px-5 py-4 text-sm text-[#475569]">{{ \App\Models\DemoRequest::INTEREST_MODULES[$demoRequest->interest_module] ?? 'Interés general' }}</td>
                                <td class="whitespace-nowrap px-5 py-4"><span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-bold {{ $statusClasses[$demoRequest->status] ?? $statusClasses['pending'] }}">{{ \App\Models\DemoRequest::STATUSES[$demoRequest->status] ?? $demoRequest->status }}</span></td>
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-[#475569]">{{ $demoRequest->created_at?->format('d/m/Y H:i') }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-right"><a href="{{ route('demo-requests.show', $demoRequest) }}" class="rounded-lg border border-[#E2E8F0] px-3 py-2 text-xs font-semibold text-[#2563EB] transition hover:border-[#2563EB] hover:bg-[#2563EB]/5">Ver detalle</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-5 py-12 text-center"><p class="text-sm font-semibold text-[#0F172A]">No hay solicitudes que coincidan con los filtros.</p><p class="mt-1 text-sm text-[#475569]">Las nuevas solicitudes enviadas desde la landing aparecerán aquí.</p></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($demoRequests->hasPages())<div class="border-t border-[#E2E8F0] px-5 py-4">{{ $demoRequests->links() }}</div>@endif
        </section>
    </div>
</x-app-layout>
