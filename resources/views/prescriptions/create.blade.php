<x-app-layout>
    <div class="space-y-6">
        <header class="flex flex-col gap-4 rounded-lg border border-[#E2E8F0] bg-white p-5 shadow-sm lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-[#2563EB]">Recetas médicas</p>
                <h1 class="mt-2 text-2xl font-bold tracking-tight text-[#0F172A] sm:text-3xl">Nueva receta</h1>
                <p class="mt-2 text-sm text-[#475569]">Registra medicamentos e indicaciones asociadas a una consulta o de forma directa.</p>
            </div>
            <a href="{{ route('prescriptions.index') }}" class="inline-flex items-center justify-center rounded-lg border border-[#E2E8F0] px-4 py-3 text-sm font-semibold text-[#475569]">Volver</a>
        </header>

        <form method="POST" action="{{ route('prescriptions.store') }}" class="overflow-hidden rounded-lg border border-[#E2E8F0] bg-white shadow-sm">
            @csrf
            @include('prescriptions._form', ['prescription' => null, 'buttonText' => 'Guardar receta'])
        </form>
    </div>
</x-app-layout>
