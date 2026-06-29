@php
    $allReportPermissions = ['reports.appointments', 'reports.clinical', 'reports.financial', 'reports.patients', 'reports.doctors', 'reports.services'];
    $canSeeGeneral = collect($allReportPermissions)->every(fn ($permission) => auth()->user()?->can($permission));
    $reportLinks = collect([
        ['route' => 'reports.index', 'label' => 'General', 'permission' => null, 'visible' => $canSeeGeneral],
        ['route' => 'reports.appointments', 'label' => 'Citas', 'permission' => 'reports.appointments'],
        ['route' => 'reports.clinical', 'label' => 'Clinico', 'permission' => 'reports.clinical'],
        ['route' => 'reports.financial', 'label' => 'Financiero', 'permission' => 'reports.financial'],
        ['route' => 'reports.patients', 'label' => 'Pacientes', 'permission' => 'reports.patients'],
        ['route' => 'reports.doctors', 'label' => 'Medicos', 'permission' => 'reports.doctors'],
        ['route' => 'reports.services', 'label' => 'Servicios', 'permission' => 'reports.services'],
    ])->filter(fn ($link) => ($link['visible'] ?? true) && (! $link['permission'] || auth()->user()?->can($link['permission'])));
    $financialQuery = request()->query();
@endphp

<section class="space-y-5">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-[#2563EB]">Reportes y Analitica</p>
            <h1 class="mt-2 text-2xl font-bold tracking-tight text-[#0F172A] sm:text-3xl">{{ $title }}</h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-[#475569]">{{ $description }}</p>
            <p class="mt-2 text-xs font-bold uppercase tracking-wide text-[#2563EB]">Periodo: {{ $periodLabel }}</p>
        </div>
        @if (request()->routeIs('reports.financial') && auth()->user()?->can('reports.financial'))
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('reports.financial.export.pdf', $financialQuery) }}" class="rounded-lg border border-[#E2E8F0] bg-white px-3 py-2 text-xs font-semibold text-[#0F172A] transition hover:border-[#2563EB] hover:text-[#2563EB]">Exportar PDF</a>
                <a href="{{ route('reports.financial.export.csv', $financialQuery) }}" class="rounded-lg border border-[#E2E8F0] bg-white px-3 py-2 text-xs font-semibold text-[#0F172A] transition hover:border-[#2563EB] hover:text-[#2563EB]">Exportar CSV</a>
                <a href="{{ route('reports.financial.export.xlsx', $financialQuery) }}" class="rounded-lg border border-[#E2E8F0] bg-white px-3 py-2 text-xs font-semibold text-[#047857] transition hover:border-[#10B981] hover:text-[#047857]">Exportar Excel</a>
                <a href="{{ route('reports.financial.print', $financialQuery) }}" class="rounded-lg bg-[#0F172A] px-3 py-2 text-xs font-semibold text-white transition hover:bg-slate-800">Imprimir</a>
            </div>
        @endif
    </div>

    @if ($reportLinks->isNotEmpty())
        <nav class="flex gap-2 overflow-x-auto rounded-lg border border-[#E2E8F0] bg-white p-2 shadow-sm" aria-label="Secciones de reportes">
            @foreach ($reportLinks as $link)
                <a href="{{ route($link['route']) }}" class="whitespace-nowrap rounded-lg px-3 py-2 text-sm font-semibold transition {{ request()->routeIs($link['route']) ? 'bg-[#2563EB] text-white' : 'text-[#475569] hover:bg-[#F8FAFC] hover:text-[#2563EB]' }}">{{ $link['label'] }}</a>
            @endforeach
        </nav>
    @endif
</section>