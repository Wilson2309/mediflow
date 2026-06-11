<x-app-layout>
    <div class="space-y-6">
        <header class="flex flex-col gap-4 rounded-lg border border-[#E2E8F0] bg-white p-5 shadow-sm lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-[#2563EB]">Recetas médicas</p>
                <h1 class="mt-2 text-2xl font-bold tracking-tight text-[#0F172A] sm:text-3xl">Editar receta</h1>
                <p class="mt-2 text-sm text-[#475569]">{{ $prescription->patient?->full_name }} · {{ $prescription->prescription_date?->format('d/m/Y') }}</p>
            </div>
            <a href="{{ route('prescriptions.show', $prescription) }}" class="inline-flex items-center justify-center rounded-lg border border-[#E2E8F0] px-4 py-3 text-sm font-semibold text-[#475569]">Volver</a>
        </header>

        <form method="POST" action="{{ route('prescriptions.update', $prescription) }}" class="overflow-hidden rounded-lg border border-[#E2E8F0] bg-white shadow-sm">
            @csrf
            @method('PUT')
            @include('prescriptions._form', ['buttonText' => 'Actualizar receta'])
        </form>
    </div>
</x-app-layout>
