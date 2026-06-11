@php
    $reportLinks = [
        ['route' => 'reports.index', 'label' => 'General'],
        ['route' => 'reports.appointments', 'label' => 'Citas'],
        ['route' => 'reports.clinical', 'label' => 'Clínico'],
        ['route' => 'reports.financial', 'label' => 'Financiero'],
        ['route' => 'reports.patients', 'label' => 'Pacientes'],
        ['route' => 'reports.doctors', 'label' => 'Médicos'],
        ['route' => 'reports.services', 'label' => 'Servicios'],
    ];
@endphp

<section class="space-y-5">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-[#2563EB]">Reportes y Analítica</p>
            <h1 class="mt-2 text-2xl font-bold tracking-tight text-[#0F172A] sm:text-3xl">{{ $title }}</h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-[#475569]">{{ $description }}</p>
            <p class="mt-2 text-xs font-bold uppercase tracking-wide text-[#2563EB]">Periodo: {{ $periodLabel }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @foreach (['Exportar PDF', 'Exportar Excel', 'Imprimir'] as $action)
                <button type="button" disabled title="Disponible en una próxima fase" class="cursor-not-allowed rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-2 text-xs font-semibold text-slate-400">{{ $action }}</button>
            @endforeach
        </div>
    </div>

    <nav class="flex gap-2 overflow-x-auto rounded-lg border border-[#E2E8F0] bg-white p-2 shadow-sm" aria-label="Secciones de reportes">
        @foreach ($reportLinks as $link)
            <a href="{{ route($link['route']) }}" class="whitespace-nowrap rounded-lg px-3 py-2 text-sm font-semibold transition {{ request()->routeIs($link['route']) ? 'bg-[#2563EB] text-white' : 'text-[#475569] hover:bg-[#F8FAFC] hover:text-[#2563EB]' }}">{{ $link['label'] }}</a>
        @endforeach
    </nav>
</section>
