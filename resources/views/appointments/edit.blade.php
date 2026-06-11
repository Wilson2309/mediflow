<x-app-layout>
    <div class="space-y-6">
        <section class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-[#2563EB]">Citas médicas</p>
                <h1 class="mt-2 text-2xl font-bold tracking-tight text-[#0F172A] sm:text-3xl">Editar cita</h1>
                <p class="mt-2 text-sm text-[#475569]">{{ $appointment->patient?->full_name }}</p>
            </div>
            <a href="{{ route('appointments.show', $appointment) }}" class="inline-flex items-center justify-center rounded-lg border border-[#E2E8F0] px-4 py-3 text-sm font-semibold text-[#475569]">Volver</a>
        </section>

        <form method="POST" action="{{ route('appointments.update', $appointment) }}" class="rounded-lg border border-[#E2E8F0] bg-white shadow-sm">
            @csrf
            @method('PUT')
            @include('appointments._form', ['appointment' => $appointment, 'buttonText' => 'Actualizar cita'])
        </form>
    </div>
</x-app-layout>
