<x-app-layout>
    <div class="space-y-6">
        <header class="flex flex-col gap-4 rounded-lg border border-[#E2E8F0] bg-white p-5 shadow-sm lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-[#2563EB]">Pagos y Finanzas</p>
                <h1 class="mt-2 text-2xl font-bold tracking-tight text-[#0F172A] sm:text-3xl">Editar pago</h1>
                <p class="mt-2 text-sm text-[#475569]">{{ $payment->patient?->full_name }} · ${{ number_format((float) $payment->amount, 2) }}</p>
            </div>
            <a href="{{ route('payments.show', $payment) }}" class="inline-flex items-center justify-center rounded-lg border border-[#E2E8F0] px-4 py-3 text-sm font-semibold text-[#475569]">Volver</a>
        </header>

        <form method="POST" action="{{ route('payments.update', $payment) }}" class="overflow-hidden rounded-lg border border-[#E2E8F0] bg-white shadow-sm" data-requires-online="true" data-offline-block-message="No se puede registrar ni modificar pagos sin conexión. Esta acción se habilitará cuando vuelva la conexión.">
            @csrf
            @method('PUT')
            @include('payments._form', ['buttonText' => 'Actualizar pago'])
        </form>
    </div>
</x-app-layout>
