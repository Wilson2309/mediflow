<x-app-layout>
    <div class="space-y-6">
        <section class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-[#2563EB]">Servicios médicos</p>
                <h1 class="mt-2 text-2xl font-bold tracking-tight text-[#0F172A] sm:text-3xl">Nuevo servicio</h1>
                <p class="mt-2 text-sm text-[#475569]">Registra un servicio clínico disponible para citas y pagos.</p>
            </div>
            <a href="{{ route('services.index') }}" class="inline-flex items-center justify-center rounded-lg border border-[#E2E8F0] px-4 py-3 text-sm font-semibold text-[#475569] transition hover:border-[#2563EB] hover:text-[#2563EB]">Volver</a>
        </section>

        <form method="POST" action="{{ route('services.store') }}" class="rounded-lg border border-[#E2E8F0] bg-white shadow-sm">
            @csrf
            @include('services._form')
            <div class="flex flex-col-reverse gap-3 border-t border-[#E2E8F0] px-5 py-4 sm:flex-row sm:justify-end">
                <a href="{{ route('services.index') }}" class="inline-flex items-center justify-center rounded-lg border border-[#E2E8F0] px-4 py-3 text-sm font-semibold text-[#475569] transition hover:border-[#2563EB] hover:text-[#2563EB]">Cancelar</a>
                <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-[#2563EB] px-4 py-3 text-sm font-semibold text-white shadow-sm shadow-blue-500/20 transition hover:bg-blue-700">Guardar servicio</button>
            </div>
        </form>
    </div>
</x-app-layout>
