<x-app-layout>
    <div class="space-y-6">
        <section class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-[#2563EB]">Citas médicas</p>
                <h1 class="mt-2 text-2xl font-bold tracking-tight text-[#0F172A] sm:text-3xl">Nueva cita</h1>
                <p class="mt-2 text-sm text-[#475569]">Programa una atención médica para un paciente.</p>
            </div>
            <a href="{{ route('appointments.index') }}" class="inline-flex items-center justify-center rounded-lg border border-[#E2E8F0] px-4 py-3 text-sm font-semibold text-[#475569]">Volver</a>
        </section>

        <form method="POST" action="{{ route('appointments.store') }}" class="rounded-lg border border-[#E2E8F0] bg-white shadow-sm" data-offline-draft="true" data-draft-form="appointments" data-draft-record="new" data-offline-draft-message="No hay conexión. El formulario fue guardado como borrador local. Revísalo y envíalo cuando vuelva la conexión.">
            @csrf
            @include('appointments._form', ['appointment' => null, 'buttonText' => 'Guardar cita'])
        </form>
    </div>
</x-app-layout>
