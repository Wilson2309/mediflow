@php
    $actionClasses = [
        'created' => 'border-[#10B981]/20 bg-[#10B981]/10 text-[#047857]',
        'updated' => 'border-[#2563EB]/20 bg-[#2563EB]/10 text-[#2563EB]',
        'paid' => 'border-[#10B981]/20 bg-[#10B981]/10 text-[#047857]',
        'cancelled' => 'border-[#EF4444]/20 bg-[#EF4444]/10 text-[#B91C1C]',
        'signed' => 'border-violet-200 bg-violet-50 text-violet-700',
        'emailed' => 'border-cyan-200 bg-cyan-50 text-cyan-700',
        'refunded' => 'border-slate-200 bg-slate-100 text-[#475569]',
        'deleted' => 'border-[#EF4444]/20 bg-[#EF4444]/10 text-[#B91C1C]',
    ];
@endphp

<x-app-layout>
    <div class="space-y-6">
        <header class="flex flex-col gap-4 rounded-lg border border-[#E2E8F0] bg-white p-5 shadow-sm lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-[#2563EB]">Administración</p>
                <h1 class="mt-2 text-2xl font-bold tracking-tight text-[#0F172A] sm:text-3xl">Auditoría del sistema</h1>
                <p class="mt-2 text-sm leading-6 text-[#475569]">Revisión de acciones importantes: quién hizo qué, cuándo y desde dónde.</p>
            </div>
        </header>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <article class="rounded-lg border border-[#E2E8F0] bg-white p-5 shadow-sm"><p class="text-sm font-semibold text-[#475569]">Acciones de hoy</p><p class="mt-3 text-2xl font-bold text-[#0F172A]">{{ number_format($actionsToday) }}</p></article>
            <article class="rounded-lg border border-[#E2E8F0] bg-white p-5 shadow-sm"><p class="text-sm font-semibold text-[#475569]">Pagos auditados hoy</p><p class="mt-3 text-2xl font-bold text-[#10B981]">{{ number_format($paymentsToday) }}</p></article>
            <article class="rounded-lg border border-[#E2E8F0] bg-white p-5 shadow-sm"><p class="text-sm font-semibold text-[#475569]">Recetas firmadas hoy</p><p class="mt-3 text-2xl font-bold text-violet-700">{{ number_format($signedPrescriptionsToday) }}</p></article>
            <article class="rounded-lg border border-[#E2E8F0] bg-white p-5 shadow-sm"><p class="text-sm font-semibold text-[#475569]">Usuarios con actividad hoy</p><p class="mt-3 text-2xl font-bold text-[#2563EB]">{{ number_format($activeUsersToday) }}</p></article>
        </section>

        <form method="GET" action="{{ route('audit-logs.index') }}" class="rounded-lg border border-[#E2E8F0] bg-white p-5 shadow-sm">
            <div class="grid gap-4 md:grid-cols-6">
                <div>
                    <label for="date_from" class="mb-2 block text-sm font-semibold text-[#0F172A]">Desde</label>
                    <input id="date_from" name="date_from" type="date" value="{{ $dateFrom }}" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
                </div>
                <div>
                    <label for="date_to" class="mb-2 block text-sm font-semibold text-[#0F172A]">Hasta</label>
                    <input id="date_to" name="date_to" type="date" value="{{ $dateTo }}" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
                </div>
                <div>
                    <label for="user_id" class="mb-2 block text-sm font-semibold text-[#0F172A]">Usuario</label>
                    <select id="user_id" name="user_id" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
                        <option value="">Todos</option>
                        @foreach ($users as $filterUser)
                            <option value="{{ $filterUser->id }}" @selected((string) $userId === (string) $filterUser->id)>{{ $filterUser->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="module" class="mb-2 block text-sm font-semibold text-[#0F172A]">Módulo</label>
                    <select id="module" name="module" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
                        <option value="">Todos</option>
                        @foreach ($modules as $moduleOption)
                            <option value="{{ $moduleOption }}" @selected($module === $moduleOption)>{{ str($moduleOption)->replace('_', ' ')->title() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="action" class="mb-2 block text-sm font-semibold text-[#0F172A]">Acción</label>
                    <select id="action" name="action" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
                        <option value="">Todas</option>
                        @foreach ($actions as $actionOption)
                            <option value="{{ $actionOption }}" @selected($action === $actionOption)>{{ $actionOption }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="search" class="mb-2 block text-sm font-semibold text-[#0F172A]">Buscar</label>
                    <input id="search" name="search" type="search" value="{{ $search }}" placeholder="Descripción, acción o ID" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
                </div>
            </div>
            <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:justify-end">
                <a href="{{ route('audit-logs.index') }}" class="inline-flex items-center justify-center rounded-lg border border-[#E2E8F0] px-4 py-2.5 text-sm font-semibold text-[#475569]">Limpiar</a>
                <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-[#0F172A] px-4 py-2.5 text-sm font-semibold text-white">Filtrar</button>
            </div>
        </form>

        <section class="overflow-hidden rounded-lg border border-[#E2E8F0] bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-[#E2E8F0]">
                    <thead class="bg-[#F8FAFC]">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-[#475569]">Fecha/Hora</th>
                            <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-[#475569]">Usuario</th>
                            <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-[#475569]">Módulo</th>
                            <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-[#475569]">Acción</th>
                            <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-[#475569]">Descripción</th>
                            <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-[#475569]">Entidad</th>
                            <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-[#475569]">IP</th>
                            <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-[#475569]">Detalle</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E2E8F0] bg-white">
                        @forelse ($logs as $log)
                            @php
                                $family = $log->action_family;
                                $badgeClass = $actionClasses[$family] ?? 'border-slate-200 bg-slate-100 text-[#475569]';
                            @endphp
                            <tr class="align-top hover:bg-[#F8FAFC]">
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-[#0F172A]">{{ $log->created_at?->format('d/m/Y H:i:s') }}</td>
                                <td class="px-5 py-4 text-sm"><p class="font-semibold text-[#0F172A]">{{ $log->user?->name ?? 'Sistema' }}</p><p class="mt-1 text-xs text-[#475569]">{{ $log->user?->email }}</p></td>
                                <td class="px-5 py-4 text-sm font-semibold text-[#475569]">{{ str($log->module ?? 'general')->replace('_', ' ')->title() }}</td>
                                <td class="px-5 py-4"><span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-bold {{ $badgeClass }}">{{ $log->action }}</span></td>
                                <td class="max-w-sm px-5 py-4 text-sm leading-6 text-[#475569]">{{ $log->description ?: 'Sin descripción.' }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-[#475569]">{{ $log->auditable_label }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-[#475569]">{{ $log->ip_address ?: 'Sin IP' }}</td>
                                <td class="px-5 py-4 text-sm text-[#475569]">
                                    @if (($log->old_values || $log->new_values) && auth()->user()?->hasRole('administrador'))
                                        <details class="min-w-64">
                                            <summary class="cursor-pointer text-xs font-bold text-[#2563EB]">Ver detalle</summary>
                                            <div class="mt-3 space-y-3 rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] p-3">
                                                @if ($log->old_values)
                                                    <div>
                                                        <p class="text-xs font-bold uppercase text-[#475569]">Antes</p>
                                                        <pre class="mt-1 max-h-40 overflow-auto whitespace-pre-wrap text-xs text-[#0F172A]">{{ json_encode($log->old_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                                    </div>
                                                @endif
                                                @if ($log->new_values)
                                                    <div>
                                                        <p class="text-xs font-bold uppercase text-[#475569]">Después</p>
                                                        <pre class="mt-1 max-h-40 overflow-auto whitespace-pre-wrap text-xs text-[#0F172A]">{{ json_encode($log->new_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                                    </div>
                                                @endif
                                            </div>
                                        </details>
                                    @else
                                        <span class="text-xs text-[#94A3B8]">Sin detalle visible</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-5 py-10 text-center text-sm text-[#475569]">No hay registros de auditoría para los filtros seleccionados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-[#E2E8F0] px-5 py-4">{{ $logs->links() }}</div>
        </section>
    </div>
</x-app-layout>