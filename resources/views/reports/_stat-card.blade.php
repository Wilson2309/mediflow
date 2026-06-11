@php
    $toneClasses = [
        'blue' => 'bg-[#2563EB]/10 text-[#2563EB]',
        'green' => 'bg-[#10B981]/10 text-[#047857]',
        'red' => 'bg-[#EF4444]/10 text-[#EF4444]',
        'yellow' => 'bg-[#F59E0B]/10 text-[#B45309]',
        'slate' => 'bg-slate-100 text-[#0F172A]',
    ];
@endphp
<article class="rounded-lg border border-[#E2E8F0] bg-white p-5 shadow-sm">
    <div class="flex items-start justify-between gap-3">
        <div><p class="text-sm font-semibold text-[#475569]">{{ $label }}</p><p class="mt-3 text-2xl font-bold tracking-tight text-[#0F172A]">{{ $value }}</p></div>
        <span class="rounded-lg px-2.5 py-1 text-xs font-bold {{ $toneClasses[$tone ?? 'blue'] }}">Real</span>
    </div>
    @isset($summary)<p class="mt-3 text-xs leading-5 text-[#475569]">{{ $summary }}</p>@endisset
</article>
