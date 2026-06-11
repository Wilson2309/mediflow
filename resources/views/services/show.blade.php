<x-app-layout>
    <div class="space-y-6">
        <section class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-[#2563EB]">Detalle del servicio</p>
                <h1 class="mt-2 text-2xl font-bold tracking-tight text-[#0F172A] sm:text-3xl">{{ $service->name }}</h1>
                <p class="mt-2 text-sm text-[#475569]">Información comercial y operativa del servicio médico.</p>
            </div>
            <div class="flex flex-col gap-3 sm:flex-row"><a href="{{ route('services.index') }}" class="inline-flex items-center justify-center rounded-lg border border-[#E2E8F0] px-4 py-3 text-sm font-semibold text-[#475569] transition hover:border-[#2563EB] hover:text-[#2563EB]">Volver</a>@can('services.update')<a href="{{ route('services.edit', $service) }}" class="inline-flex items-center justify-center rounded-lg bg-[#2563EB] px-4 py-3 text-sm font-semibold text-white shadow-sm shadow-blue-500/20 transition hover:bg-blue-700">Editar servicio</a>@endcan</div>
        </section>

        @if (session('success'))<div class="rounded-lg border border-[#10B981]/20 bg-[#10B981]/10 px-4 py-3 text-sm font-semibold text-[#047857]">{{ session('success') }}</div>@endif

        <section class="grid gap-6 xl:grid-cols-3">
            <div class="space-y-6 xl:col-span-2">
                <article class="rounded-lg border border-[#E2E8F0] bg-white shadow-sm"><div class="border-b border-[#E2E8F0] px-5 py-4"><h2 class="text-base font-bold text-[#0F172A]">Información del servicio</h2></div><div class="grid gap-5 p-5 sm:grid-cols-2"><div><p class="text-xs font-bold uppercase tracking-wide text-[#475569]">Nombre</p><p class="mt-1 text-sm font-semibold text-[#0F172A]">{{ $service->name }}</p></div><div><p class="text-xs font-bold uppercase tracking-wide text-[#475569]">Precio</p><p class="mt-1 text-sm font-semibold text-[#0F172A]">${{ number_format((float) $service->price, 2) }}</p></div><div><p class="text-xs font-bold uppercase tracking-wide text-[#475569]">Duración</p><p class="mt-1 text-sm text-[#0F172A]">{{ $service->duration_minutes }} minutos</p></div><div><p class="text-xs font-bold uppercase tracking-wide text-[#475569]">Estado</p><div class="mt-1">@if ($service->status === 'active')<span class="inline-flex rounded-full border border-[#10B981]/20 bg-[#10B981]/10 px-2.5 py-1 text-xs font-bold text-[#10B981]">Activo</span>@else<span class="inline-flex rounded-full border border-slate-200 bg-slate-100 px-2.5 py-1 text-xs font-bold text-[#475569]">Inactivo</span>@endif</div></div><div class="sm:col-span-2"><p class="text-xs font-bold uppercase tracking-wide text-[#475569]">Descripción</p><p class="mt-1 whitespace-pre-line text-sm leading-6 text-[#0F172A]">{{ $service->description ?: 'Sin descripción registrada.' }}</p></div></div></article>
                <div class="grid gap-4 sm:grid-cols-2"><article class="rounded-lg border border-[#E2E8F0] bg-white p-5 shadow-sm"><p class="text-sm font-semibold text-[#475569]">Citas relacionadas</p><p class="mt-3 text-3xl font-bold text-[#0F172A]">{{ number_format($service->appointments_count) }}</p></article><article class="rounded-lg border border-[#E2E8F0] bg-white p-5 shadow-sm"><p class="text-sm font-semibold text-[#475569]">Pagos relacionados</p><p class="mt-3 text-3xl font-bold text-[#0F172A]">{{ number_format($service->payments_count) }}</p></article></div>
            </div>
            <aside><article class="rounded-lg border border-[#E2E8F0] bg-white shadow-sm"><div class="border-b border-[#E2E8F0] px-5 py-4"><h2 class="text-base font-bold text-[#0F172A]">Fechas</h2></div><div class="space-y-4 p-5"><div><p class="text-xs font-bold uppercase tracking-wide text-[#475569]">Creación</p><p class="mt-1 text-sm text-[#0F172A]">{{ $service->created_at?->format('d/m/Y H:i') }}</p></div><div><p class="text-xs font-bold uppercase tracking-wide text-[#475569]">Actualización</p><p class="mt-1 text-sm text-[#0F172A]">{{ $service->updated_at?->format('d/m/Y H:i') }}</p></div></div></article></aside>
        </section>
    </div>
</x-app-layout>
