@php
    $oldItems = old('items');
    $items = collect($oldItems ?? ($prescription?->items?->map(fn ($item) => [
        'medication_name' => $item->medication_name,
        'dosage' => $item->dosage,
        'frequency' => $item->frequency,
        'duration' => $item->duration,
        'instructions' => $item->instructions,
    ])->all() ?? []));

    if ($items->isEmpty()) {
        $items = collect([['medication_name' => '', 'dosage' => '', 'frequency' => '', 'duration' => '', 'instructions' => '']]);
    }
@endphp

<div class="space-y-6 p-5">
    <section>
        <h2 class="text-base font-bold text-[#0F172A]">Información general</h2>
        <div class="mt-4 grid gap-5 md:grid-cols-2">
            <div class="md:col-span-2">
                <label for="consultation_id" class="mb-2 block text-sm font-semibold text-[#0F172A]">Consulta asociada</label>
                <select id="consultation_id" name="consultation_id" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
                    <option value="">Sin consulta asociada</option>
                    @foreach ($consultations as $consultation)
                        <option value="{{ $consultation->id }}" @selected((string) old('consultation_id', $prescription?->consultation_id ?? ($prefill['consultation_id'] ?? '')) === (string) $consultation->id)>
                            {{ $consultation->consultation_date?->format('d/m/Y H:i') }} · {{ $consultation->patient?->full_name }} · {{ $consultation->doctor?->user?->name }}
                        </option>
                    @endforeach
                </select>
                @error('consultation_id') <p class="mt-2 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="patient_id" class="mb-2 block text-sm font-semibold text-[#0F172A]">Paciente *</label>
                <select id="patient_id" name="patient_id" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
                    <option value="">Seleccione un paciente</option>
                    @foreach ($patients as $patient)
                        <option value="{{ $patient->id }}" @selected((string) old('patient_id', $prescription?->patient_id ?? ($prefill['patient_id'] ?? '')) === (string) $patient->id)>{{ $patient->full_name }} - {{ $patient->identification_number ?: 'Sin identificación' }}</option>
                    @endforeach
                </select>
                @error('patient_id') <p class="mt-2 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="doctor_id" class="mb-2 block text-sm font-semibold text-[#0F172A]">Médico *</label>
                <select id="doctor_id" name="doctor_id" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
                    <option value="">Seleccione un médico</option>
                    @foreach ($doctors as $doctor)
                        <option value="{{ $doctor->id }}" @selected((string) old('doctor_id', $prescription?->doctor_id ?? ($prefill['doctor_id'] ?? '')) === (string) $doctor->id)>{{ $doctor->user?->name }}{{ $doctor->specialty ? ' - '.$doctor->specialty->name : '' }}</option>
                    @endforeach
                </select>
                @error('doctor_id') <p class="mt-2 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="prescription_date" class="mb-2 block text-sm font-semibold text-[#0F172A]">Fecha *</label>
                <input id="prescription_date" name="prescription_date" type="date" value="{{ old('prescription_date', $prescription?->prescription_date?->format('Y-m-d')) }}" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
                @error('prescription_date') <p class="mt-2 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="status" class="mb-2 block text-sm font-semibold text-[#0F172A]">Estado *</label>
                <select id="status" name="status" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
                    <option value="active" @selected(old('status', $prescription?->status ?? 'active') === 'active')>Activa</option>
                    <option value="cancelled" @selected(old('status', $prescription?->status) === 'cancelled')>Cancelada</option>
                </select>
                @error('status') <p class="mt-2 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
            </div>
            <div class="md:col-span-2">
                <label for="general_instructions" class="mb-2 block text-sm font-semibold text-[#0F172A]">Instrucciones generales</label>
                <textarea id="general_instructions" name="general_instructions" rows="3" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">{{ old('general_instructions', $prescription?->general_instructions) }}</textarea>
                @error('general_instructions') <p class="mt-2 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
            </div>
        </div>
    </section>

    <section>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-base font-bold text-[#0F172A]">Medicamentos e indicaciones</h2>
                <p class="mt-1 text-sm text-[#475569]">Agrega al menos un medicamento para guardar la receta.</p>
            </div>
            <button type="button" id="add-prescription-item" class="inline-flex items-center justify-center rounded-lg border border-[#2563EB] px-4 py-2.5 text-sm font-semibold text-[#2563EB]">Agregar medicamento</button>
        </div>
        @error('items') <p class="mt-3 text-sm text-[#EF4444]">{{ $message }}</p> @enderror

        <div id="prescription-items" class="mt-4 space-y-4">
            @foreach ($items as $index => $item)
                <div class="prescription-item rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] p-4">
                    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <label class="mb-2 block text-sm font-semibold text-[#0F172A]">Medicamento *</label>
                            <input name="items[{{ $index }}][medication_name]" type="text" value="{{ $item['medication_name'] ?? '' }}" class="w-full rounded-lg border-[#E2E8F0] bg-white text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
                            @error("items.$index.medication_name") <p class="mt-2 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-semibold text-[#0F172A]">Dosis</label>
                            <input name="items[{{ $index }}][dosage]" type="text" value="{{ $item['dosage'] ?? '' }}" class="w-full rounded-lg border-[#E2E8F0] bg-white text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-semibold text-[#0F172A]">Frecuencia</label>
                            <input name="items[{{ $index }}][frequency]" type="text" value="{{ $item['frequency'] ?? '' }}" class="w-full rounded-lg border-[#E2E8F0] bg-white text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-semibold text-[#0F172A]">Duración</label>
                            <input name="items[{{ $index }}][duration]" type="text" value="{{ $item['duration'] ?? '' }}" class="w-full rounded-lg border-[#E2E8F0] bg-white text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
                        </div>
                        <div class="md:col-span-2 lg:col-span-4">
                            <label class="mb-2 block text-sm font-semibold text-[#0F172A]">Instrucciones</label>
                            <textarea name="items[{{ $index }}][instructions]" rows="2" class="w-full rounded-lg border-[#E2E8F0] bg-white text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">{{ $item['instructions'] ?? '' }}</textarea>
                        </div>
                    </div>
                    <div class="mt-3 flex justify-end">
                        <button type="button" class="remove-prescription-item rounded-lg border border-[#EF4444]/30 px-3 py-2 text-xs font-semibold text-[#EF4444]">Quitar</button>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    @error('clinic_id') <p class="text-sm text-[#EF4444]">{{ $message }}</p> @enderror
</div>

<div class="flex flex-col-reverse gap-3 border-t border-[#E2E8F0] px-5 py-4 sm:flex-row sm:justify-end">
    <a href="{{ route('prescriptions.index') }}" class="inline-flex items-center justify-center rounded-lg border border-[#E2E8F0] px-4 py-3 text-sm font-semibold text-[#475569]">Cancelar</a>
    <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-[#2563EB] px-4 py-3 text-sm font-semibold text-white shadow-sm shadow-blue-500/20 transition hover:bg-blue-700">{{ $buttonText }}</button>
</div>

<template id="prescription-item-template">
    <div class="prescription-item rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] p-4">
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
            <div><label class="mb-2 block text-sm font-semibold text-[#0F172A]">Medicamento *</label><input data-name="medication_name" type="text" class="w-full rounded-lg border-[#E2E8F0] bg-white text-sm focus:border-[#2563EB] focus:ring-[#2563EB]"></div>
            <div><label class="mb-2 block text-sm font-semibold text-[#0F172A]">Dosis</label><input data-name="dosage" type="text" class="w-full rounded-lg border-[#E2E8F0] bg-white text-sm focus:border-[#2563EB] focus:ring-[#2563EB]"></div>
            <div><label class="mb-2 block text-sm font-semibold text-[#0F172A]">Frecuencia</label><input data-name="frequency" type="text" class="w-full rounded-lg border-[#E2E8F0] bg-white text-sm focus:border-[#2563EB] focus:ring-[#2563EB]"></div>
            <div><label class="mb-2 block text-sm font-semibold text-[#0F172A]">Duración</label><input data-name="duration" type="text" class="w-full rounded-lg border-[#E2E8F0] bg-white text-sm focus:border-[#2563EB] focus:ring-[#2563EB]"></div>
            <div class="md:col-span-2 lg:col-span-4"><label class="mb-2 block text-sm font-semibold text-[#0F172A]">Instrucciones</label><textarea data-name="instructions" rows="2" class="w-full rounded-lg border-[#E2E8F0] bg-white text-sm focus:border-[#2563EB] focus:ring-[#2563EB]"></textarea></div>
        </div>
        <div class="mt-3 flex justify-end"><button type="button" class="remove-prescription-item rounded-lg border border-[#EF4444]/30 px-3 py-2 text-xs font-semibold text-[#EF4444]">Quitar</button></div>
    </div>
</template>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const container = document.getElementById('prescription-items');
        const template = document.getElementById('prescription-item-template');
        const addButton = document.getElementById('add-prescription-item');

        function reindexItems() {
            container.querySelectorAll('.prescription-item').forEach((row, index) => {
                row.querySelectorAll('[data-name], input[name], textarea[name]').forEach((field) => {
                    const fieldName = field.dataset.name || field.name.match(/\]\[(.+)\]/)?.[1];
                    if (fieldName) {
                        field.name = `items[${index}][${fieldName}]`;
                    }
                });
            });
        }

        addButton?.addEventListener('click', () => {
            container.appendChild(template.content.cloneNode(true));
            reindexItems();
        });

        container?.addEventListener('click', (event) => {
            if (! event.target.classList.contains('remove-prescription-item')) {
                return;
            }

            if (container.querySelectorAll('.prescription-item').length > 1) {
                event.target.closest('.prescription-item').remove();
                reindexItems();
            }
        });

        reindexItems();
    });
</script>
