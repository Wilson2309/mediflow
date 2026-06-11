@php
    $methodLabels = ['cash' => 'Efectivo', 'card' => 'Tarjeta', 'transfer' => 'Transferencia', 'other' => 'Otro'];
    $statusLabels = ['pending' => 'Pendiente', 'paid' => 'Pagado', 'cancelled' => 'Cancelado', 'refunded' => 'Reembolsado'];
@endphp

<div class="grid gap-5 p-5 md:grid-cols-2">
    <div>
        <label for="patient_id" class="mb-2 block text-sm font-semibold text-[#0F172A]">Paciente *</label>
        <select id="patient_id" name="patient_id" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
            <option value="">Seleccione un paciente</option>
            @foreach ($patients as $patient)
                <option value="{{ $patient->id }}" @selected((string) old('patient_id', $payment?->patient_id) === (string) $patient->id)>{{ $patient->full_name }} - {{ $patient->identification_number ?: 'Sin identificación' }}</option>
            @endforeach
        </select>
        @error('patient_id') <p class="mt-2 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="appointment_id" class="mb-2 block text-sm font-semibold text-[#0F172A]">Cita asociada</label>
        <select id="appointment_id" name="appointment_id" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
            <option value="">Sin cita asociada</option>
            @foreach ($appointments as $appointment)
                <option value="{{ $appointment->id }}" @selected((string) old('appointment_id', $payment?->appointment_id) === (string) $appointment->id)>
                    {{ $appointment->appointment_date?->format('d/m/Y') }} {{ substr((string) $appointment->start_time, 0, 5) }} · {{ $appointment->patient?->full_name }} · {{ $appointment->doctor?->user?->name }}
                </option>
            @endforeach
        </select>
        @error('appointment_id') <p class="mt-2 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="service_id" class="mb-2 block text-sm font-semibold text-[#0F172A]">Servicio</label>
        <select id="service_id" name="service_id" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
            <option value="">Sin servicio</option>
            @foreach ($services as $service)
                <option value="{{ $service->id }}" data-price="{{ $service->price }}" @selected((string) old('service_id', $payment?->service_id) === (string) $service->id)>{{ $service->name }} - ${{ number_format((float) $service->price, 2) }}</option>
            @endforeach
        </select>
        @error('service_id') <p class="mt-2 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="amount" class="mb-2 block text-sm font-semibold text-[#0F172A]">Monto *</label>
        <input id="amount" name="amount" type="number" step="0.01" min="0.01" value="{{ old('amount', $payment?->amount) }}" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
        @error('amount') <p class="mt-2 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="payment_method" class="mb-2 block text-sm font-semibold text-[#0F172A]">Método *</label>
        <select id="payment_method" name="payment_method" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
            @foreach ($methodLabels as $value => $label)
                <option value="{{ $value }}" @selected(old('payment_method', $payment?->payment_method ?? 'cash') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('payment_method') <p class="mt-2 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="payment_status" class="mb-2 block text-sm font-semibold text-[#0F172A]">Estado *</label>
        <select id="payment_status" name="payment_status" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
            @foreach ($statusLabels as $value => $label)
                <option value="{{ $value }}" @selected(old('payment_status', $payment?->payment_status ?? 'pending') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('payment_status') <p class="mt-2 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="payment_date" class="mb-2 block text-sm font-semibold text-[#0F172A]">Fecha de pago</label>
        <input id="payment_date" name="payment_date" type="datetime-local" value="{{ old('payment_date', $payment?->payment_date?->format('Y-m-d\TH:i')) }}" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
        @error('payment_date') <p class="mt-2 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
    </div>
    <div class="md:col-span-2">
        <label for="notes" class="mb-2 block text-sm font-semibold text-[#0F172A]">Notas</label>
        <textarea id="notes" name="notes" rows="3" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">{{ old('notes', $payment?->notes) }}</textarea>
        @error('notes') <p class="mt-2 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
    </div>
    @error('clinic_id') <p class="md:col-span-2 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
</div>
<div class="flex flex-col-reverse gap-3 border-t border-[#E2E8F0] px-5 py-4 sm:flex-row sm:justify-end">
    <a href="{{ route('payments.index') }}" class="inline-flex items-center justify-center rounded-lg border border-[#E2E8F0] px-4 py-3 text-sm font-semibold text-[#475569]">Cancelar</a>
    <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-[#2563EB] px-4 py-3 text-sm font-semibold text-white shadow-sm shadow-blue-500/20 transition hover:bg-blue-700">{{ $buttonText }}</button>
</div>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const service = document.getElementById('service_id');
        const amount = document.getElementById('amount');
        service?.addEventListener('change', () => {
            const price = service.selectedOptions[0]?.dataset.price;
            if (price && (! amount.value || Number(amount.value) === 0)) {
                amount.value = Number(price).toFixed(2);
            }
        });
    });
</script>
