<x-app-layout>
    <div class="space-y-6">
        <header class="flex flex-col gap-4 rounded-lg border border-[#E2E8F0] bg-white p-5 shadow-sm lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-[#2563EB]">Historial clínico</p>
                <h1 class="mt-2 text-2xl font-bold tracking-tight text-[#0F172A] sm:text-3xl">Editar historial</h1>
                <p class="mt-2 text-sm leading-6 text-[#475569]">{{ $medicalRecord->patient?->full_name }}</p>
            </div>
            <div class="flex flex-col gap-3 sm:flex-row">
                <a href="{{ route('medical-records.show', $medicalRecord) }}" class="inline-flex items-center justify-center rounded-lg border border-[#E2E8F0] px-4 py-3 text-sm font-semibold text-[#475569]">Volver</a>
            </div>
        </header>

        <form method="POST" action="{{ route('medical-records.update', $medicalRecord) }}">
            @include('medical-records._form', [
                'method' => 'PUT',
                'submitLabel' => 'Actualizar historial',
            ])
        </form>
    </div>
</x-app-layout>
