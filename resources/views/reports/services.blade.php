@php $statusOptions = ['active' => 'Activo', 'inactive' => 'Inactivo']; @endphp
<x-app-layout><div class="space-y-6">
    @include('reports._header', ['title' => 'Reporte de servicios', 'description' => 'Uso, facturación, precios y duración de los servicios médicos.'])
    @include('reports._filters', ['routeName' => 'reports.services', 'showStatus' => true, 'showService' => true])
    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @include('reports._stat-card', ['label' => 'Servicios activos', 'value' => number_format($metrics['active']), 'tone' => 'green'])
        @include('reports._stat-card', ['label' => 'Servicios inactivos', 'value' => number_format($metrics['inactive']), 'tone' => 'slate'])
        @include('reports._stat-card', ['label' => 'Precio promedio', 'value' => '$'.number_format($metrics['averagePrice'],2)])
        @include('reports._stat-card', ['label' => 'Duración promedio', 'value' => number_format($metrics['averageDuration'],1).' min', 'tone' => 'slate'])
        @include('reports._stat-card', ['label' => 'Citas con servicio', 'value' => number_format($metrics['totalAppointments'])])
        @include('reports._stat-card', ['label' => 'Ingresos pagados', 'value' => '$'.number_format($metrics['paidIncome'],2), 'tone' => 'green'])
    </section>
    <section class="overflow-hidden rounded-lg border border-[#E2E8F0] bg-white shadow-sm"><div class="overflow-x-auto"><table class="min-w-full divide-y divide-[#E2E8F0]"><thead class="bg-[#F8FAFC]"><tr>@foreach(['Servicio','Precio','Duración','Estado','Citas asociadas','Pagos asociados','Ingresos pagados'] as $heading)<th class="px-5 py-3 text-left text-xs font-bold uppercase text-[#475569]">{{ $heading }}</th>@endforeach</tr></thead><tbody class="divide-y divide-[#E2E8F0]">@forelse($servicesReport as $service)<tr><td class="px-5 py-4 text-sm font-semibold">{{ $service->name }}</td><td class="px-5 py-4 text-sm">${{ number_format((float)$service->price,2) }}</td><td class="px-5 py-4 text-sm">{{ $service->duration_minutes }} min</td><td class="px-5 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $service->status === 'active' ? 'bg-[#10B981]/10 text-[#047857]' : 'bg-slate-100 text-[#475569]' }}">{{ $statusOptions[$service->status] }}</span></td><td class="px-5 py-4 text-sm font-bold">{{ $service->appointments_count }}</td><td class="px-5 py-4 text-sm font-bold">{{ $service->payments_count }}</td><td class="px-5 py-4 text-sm font-bold text-[#10B981]">${{ number_format((float)$service->paid_income,2) }}</td></tr>@empty<tr><td colspan="7">@include('reports._empty-state')</td></tr>@endforelse</tbody></table></div><div class="border-t border-[#E2E8F0] px-5 py-4">{{ $servicesReport->links() }}</div></section>
</div></x-app-layout>
