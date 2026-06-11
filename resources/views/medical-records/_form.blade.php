@php
    $selectedPatient = old('patient_id', request('patient_id', $medicalRecord?->patient_id));
@endphp

@csrf
@if (($method ?? 'POST') !== 'POST')
    @method($method)
@endif

<div class="grid gap-5">
    <section class="rounded-lg border border-[#E2E8F0] bg-white p-5 shadow-sm">
        <h2 class="text-base font-bold text-[#0F172A]">Paciente</h2>
        <div class="mt-4">
            <label for="patient_id" class="mb-2 block text-sm font-semibold text-[#0F172A]">Paciente</label>
            <select id="patient_id" name="patient_id" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
                <option value="">Seleccionar paciente</option>
                @foreach ($patients as $patient)
                    <option value="{{ $patient->id }}" @selected((string) $selectedPatient === (string) $patient->id)>
                        {{ $patient->full_name }} {{ $patient->identification_number ? '· '.$patient->identification_number : '' }}
                    </option>
                @endforeach
            </select>
            @error('patient_id') <p class="mt-2 text-sm font-semibold text-[#EF4444]">{{ $message }}</p> @enderror
            @error('clinic_id') <p class="mt-2 text-sm font-semibold text-[#EF4444]">{{ $message }}</p> @enderror
            @if ($patients->isEmpty())
                <p class="mt-2 text-sm text-[#475569]">No hay pacientes disponibles sin historial clínico.</p>
            @endif
        </div>
    </section>

    <section class="rounded-lg border border-[#E2E8F0] bg-white p-5 shadow-sm">
        <h2 class="text-base font-bold text-[#0F172A]">Antecedentes clínicos</h2>
        <div class="mt-4 grid gap-4 lg:grid-cols-2">
            <div>
                <label for="personal_history" class="mb-2 block text-sm font-semibold text-[#0F172A]">Antecedentes personales</label>
                <textarea id="personal_history" name="personal_history" rows="5" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">{{ old('personal_history', $medicalRecord?->personal_history) }}</textarea>
                @error('personal_history') <p class="mt-2 text-sm font-semibold text-[#EF4444]">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="family_history" class="mb-2 block text-sm font-semibold text-[#0F172A]">Antecedentes familiares</label>
                <textarea id="family_history" name="family_history" rows="5" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">{{ old('family_history', $medicalRecord?->family_history) }}</textarea>
                @error('family_history') <p class="mt-2 text-sm font-semibold text-[#EF4444]">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="surgical_history" class="mb-2 block text-sm font-semibold text-[#0F172A]">Antecedentes quirúrgicos</label>
                <textarea id="surgical_history" name="surgical_history" rows="5" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">{{ old('surgical_history', $medicalRecord?->surgical_history) }}</textarea>
                @error('surgical_history') <p class="mt-2 text-sm font-semibold text-[#EF4444]">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="current_medications" class="mb-2 block text-sm font-semibold text-[#0F172A]">Medicamentos actuales</label>
                <textarea id="current_medications" name="current_medications" rows="5" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">{{ old('current_medications', $medicalRecord?->current_medications) }}</textarea>
                @error('current_medications') <p class="mt-2 text-sm font-semibold text-[#EF4444]">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="chronic_diseases" class="mb-2 block text-sm font-semibold text-[#0F172A]">Enfermedades crónicas</label>
                <textarea id="chronic_diseases" name="chronic_diseases" rows="5" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">{{ old('chronic_diseases', $medicalRecord?->chronic_diseases) }}</textarea>
                @error('chronic_diseases') <p class="mt-2 text-sm font-semibold text-[#EF4444]">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="observations" class="mb-2 block text-sm font-semibold text-[#0F172A]">Observaciones generales</label>
                <textarea id="observations" name="observations" rows="5" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">{{ old('observations', $medicalRecord?->observations) }}</textarea>
                @error('observations') <p class="mt-2 text-sm font-semibold text-[#EF4444]">{{ $message }}</p> @enderror
            </div>
        </div>
    </section>

    <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
        <a href="{{ route('medical-records.index') }}" class="inline-flex items-center justify-center rounded-lg border border-[#E2E8F0] px-4 py-3 text-sm font-semibold text-[#475569]">Cancelar</a>
        <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-[#2563EB] px-4 py-3 text-sm font-semibold text-white shadow-sm shadow-blue-500/20 hover:bg-blue-700">
            {{ $submitLabel }}
        </button>
    </div>
</div>
