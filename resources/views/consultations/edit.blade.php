<x-app-layout>
    <div class="space-y-6">
        <header class="flex flex-col gap-4 rounded-lg border border-[#E2E8F0] bg-white p-5 shadow-sm lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-[#2563EB]">Consultas médicas</p>
                <h1 class="mt-2 text-2xl font-bold tracking-tight text-[#0F172A] sm:text-3xl">Editar consulta</h1>
                <p class="mt-2 text-sm text-[#475569]">{{ $consultation->patient?->full_name }} · {{ $consultation->consultation_date?->format('d/m/Y H:i') }}</p>
            </div>
            <a href="{{ route('consultations.show', $consultation) }}" class="inline-flex items-center justify-center rounded-lg border border-[#E2E8F0] px-4 py-3 text-sm font-semibold text-[#475569]">Volver</a>
        </header>

        <form method="POST" action="{{ route('consultations.update', $consultation) }}" class="overflow-hidden rounded-lg border border-[#E2E8F0] bg-white shadow-sm" data-offline-draft="true" data-draft-form="consultations" data-draft-record="{{ $consultation->id }}" data-offline-draft-message="No hay conexión. El contenido fue guardado como borrador local. Revísalo y envíalo cuando vuelva la conexión.">
            @csrf
            @method('PUT')
            @include('consultations._form', ['buttonText' => 'Actualizar consulta'])
        </form>
    </div>
</x-app-layout>
