<div class="space-y-6 p-5">
    <section>
        <h2 class="text-base font-bold text-[#0F172A]">Información general</h2>
        <div class="mt-4 grid gap-5 md:grid-cols-2">
            <div class="md:col-span-2">
                <label for="appointment_id" class="mb-2 block text-sm font-semibold text-[#0F172A]">Cita asociada</label>
                <select id="appointment_id" name="appointment_id" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
                    <option value="">Sin cita asociada</option>
                    @foreach ($appointments as $appointment)
                        <option value="{{ $appointment->id }}" @selected((string) old('appointment_id', $consultation?->appointment_id) === (string) $appointment->id)>
                            {{ $appointment->appointment_date?->format('d/m/Y') }} {{ substr((string) $appointment->start_time, 0, 5) }} · {{ $appointment->patient?->full_name }} · {{ $appointment->doctor?->user?->name }}
                        </option>
                    @endforeach
                </select>
                @error('appointment_id') <p class="mt-2 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="patient_id" class="mb-2 block text-sm font-semibold text-[#0F172A]">Paciente *</label>
                <select id="patient_id" name="patient_id" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
                    <option value="">Seleccione un paciente</option>
                    @foreach ($patients as $patient)
                        <option value="{{ $patient->id }}" @selected((string) old('patient_id', $consultation?->patient_id) === (string) $patient->id)>{{ $patient->full_name }} - {{ $patient->identification_number ?: 'Sin identificación' }}</option>
                    @endforeach
                </select>
                @error('patient_id') <p class="mt-2 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="doctor_id" class="mb-2 block text-sm font-semibold text-[#0F172A]">Médico *</label>
                <select id="doctor_id" name="doctor_id" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
                    <option value="">Seleccione un médico</option>
                    @foreach ($doctors as $doctor)
                        <option value="{{ $doctor->id }}" @selected((string) old('doctor_id', $consultation?->doctor_id) === (string) $doctor->id)>{{ $doctor->user?->name }}{{ $doctor->specialty ? ' - '.$doctor->specialty->name : '' }}</option>
                    @endforeach
                </select>
                @error('doctor_id') <p class="mt-2 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="consultation_date" class="mb-2 block text-sm font-semibold text-[#0F172A]">Fecha y hora *</label>
                <input id="consultation_date" name="consultation_date" type="datetime-local" value="{{ old('consultation_date', $consultation?->consultation_date?->format('Y-m-d\TH:i')) }}" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
                @error('consultation_date') <p class="mt-2 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="blood_pressure" class="mb-2 block text-sm font-semibold text-[#0F172A]">Presión arterial</label>
                <input id="blood_pressure" name="blood_pressure" type="text" value="{{ old('blood_pressure', $consultation?->blood_pressure) }}" placeholder="120/80" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
                @error('blood_pressure') <p class="mt-2 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
            </div>
        </div>
    </section>

    <section>
        <h2 class="text-base font-bold text-[#0F172A]">Datos clínicos</h2>
        <div class="mt-4 grid gap-5 md:grid-cols-2">
            <div class="md:col-span-2">
                <label for="reason" class="mb-2 block text-sm font-semibold text-[#0F172A]">Motivo de consulta</label>
                <textarea id="reason" name="reason" rows="3" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">{{ old('reason', $consultation?->reason) }}</textarea>
                @error('reason') <p class="mt-2 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="symptoms" class="mb-2 block text-sm font-semibold text-[#0F172A]">Síntomas</label>
                <textarea id="symptoms" name="symptoms" rows="4" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">{{ old('symptoms', $consultation?->symptoms) }}</textarea>
                @error('symptoms') <p class="mt-2 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="diagnosis" class="mb-2 block text-sm font-semibold text-[#0F172A]">Diagnóstico</label>
                <textarea id="diagnosis" name="diagnosis" rows="4" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">{{ old('diagnosis', $consultation?->diagnosis) }}</textarea>
                @error('diagnosis') <p class="mt-2 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="treatment" class="mb-2 block text-sm font-semibold text-[#0F172A]">Tratamiento</label>
                <textarea id="treatment" name="treatment" rows="4" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">{{ old('treatment', $consultation?->treatment) }}</textarea>
                @error('treatment') <p class="mt-2 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="observations" class="mb-2 block text-sm font-semibold text-[#0F172A]">Observaciones</label>
                <textarea id="observations" name="observations" rows="4" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">{{ old('observations', $consultation?->observations) }}</textarea>
                @error('observations') <p class="mt-2 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
            </div>
        </div>
    </section>

    <section>
        <h2 class="text-base font-bold text-[#0F172A]">Signos vitales</h2>
        <div class="mt-4 grid gap-5 md:grid-cols-4">
            <div>
                <label for="weight" class="mb-2 block text-sm font-semibold text-[#0F172A]">Peso kg</label>
                <input id="weight" name="weight" type="number" step="0.01" min="0" value="{{ old('weight', $consultation?->weight) }}" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
                @error('weight') <p class="mt-2 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="height" class="mb-2 block text-sm font-semibold text-[#0F172A]">Altura m</label>
                <input id="height" name="height" type="number" step="0.01" min="0" value="{{ old('height', $consultation?->height) }}" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
                @error('height') <p class="mt-2 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="temperature" class="mb-2 block text-sm font-semibold text-[#0F172A]">Temperatura °C</label>
                <input id="temperature" name="temperature" type="number" step="0.1" min="0" value="{{ old('temperature', $consultation?->temperature) }}" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
                @error('temperature') <p class="mt-2 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="heart_rate" class="mb-2 block text-sm font-semibold text-[#0F172A]">Frecuencia cardíaca</label>
                <input id="heart_rate" name="heart_rate" type="number" min="0" value="{{ old('heart_rate', $consultation?->heart_rate) }}" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
                @error('heart_rate') <p class="mt-2 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
            </div>
        </div>
    </section>

    @error('clinic_id') <p class="text-sm text-[#EF4444]">{{ $message }}</p> @enderror
</div>

<div class="flex flex-col-reverse gap-3 border-t border-[#E2E8F0] px-5 py-4 sm:flex-row sm:justify-end">
    <a href="{{ route('consultations.index') }}" class="inline-flex items-center justify-center rounded-lg border border-[#E2E8F0] px-4 py-3 text-sm font-semibold text-[#475569]">Cancelar</a>
    <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-[#2563EB] px-4 py-3 text-sm font-semibold text-white shadow-sm shadow-blue-500/20 transition hover:bg-blue-700">{{ $buttonText }}</button>
</div>
