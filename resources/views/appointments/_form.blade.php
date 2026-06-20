@php
    $prefillPatientId = $prefillPatientId ?? null;
    $statusLabels = [
        'scheduled' => 'Programada',
        'confirmed' => 'Confirmada',
        'completed' => 'Completada',
        'cancelled' => 'Cancelada',
        'no_show' => 'No asistió',
    ];
@endphp

<div class="grid gap-5 p-5 md:grid-cols-2">
    <div>
        <label for="patient_id" class="mb-2 block text-sm font-semibold text-[#0F172A]">Paciente *</label>
        <select id="patient_id" name="patient_id" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
            <option value="">Seleccione un paciente</option>
            @foreach ($patients as $patient)
                <option value="{{ $patient->id }}" @selected((string) old('patient_id', $appointment?->patient_id ?? $prefillPatientId) === (string) $patient->id)>{{ $patient->full_name }} - {{ $patient->identification_number ?: 'Sin identificación' }}</option>
            @endforeach
        </select>
        @error('patient_id') <p class="mt-2 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="doctor_id" class="mb-2 block text-sm font-semibold text-[#0F172A]">Médico *</label>
        <select id="doctor_id" name="doctor_id" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
            <option value="">Seleccione un médico</option>
            @foreach ($doctors as $doctor)
                <option value="{{ $doctor->id }}" @selected((string) old('doctor_id', $appointment?->doctor_id) === (string) $doctor->id)>{{ $doctor->user?->name }}{{ $doctor->specialty ? ' - '.$doctor->specialty->name : '' }}</option>
            @endforeach
        </select>
        @error('doctor_id') <p class="mt-2 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="service_id" class="mb-2 block text-sm font-semibold text-[#0F172A]">Servicio</label>
        <select id="service_id" name="service_id" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
            <option value="">Sin servicio</option>
            @foreach ($services as $service)
                <option value="{{ $service->id }}" @selected((string) old('service_id', $appointment?->service_id) === (string) $service->id)>{{ $service->name }} - ${{ number_format((float) $service->price, 2) }} - {{ $service->duration_minutes }} min</option>
            @endforeach
        </select>
        @error('service_id') <p class="mt-2 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="appointment_date" class="mb-2 block text-sm font-semibold text-[#0F172A]">Fecha *</label>
        <input id="appointment_date" name="appointment_date" type="date" value="{{ old('appointment_date', $appointment?->appointment_date?->format('Y-m-d')) }}" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
        @error('appointment_date') <p class="mt-2 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="start_time" class="mb-2 block text-sm font-semibold text-[#0F172A]">Hora inicio *</label>
        <input id="start_time" name="start_time" type="time" value="{{ old('start_time', $appointment ? substr((string) $appointment->start_time, 0, 5) : '') }}" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
        @error('start_time') <p class="mt-2 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="end_time" class="mb-2 block text-sm font-semibold text-[#0F172A]">Hora fin</label>
        <input id="end_time" name="end_time" type="time" value="{{ old('end_time', $appointment && $appointment->end_time ? substr((string) $appointment->end_time, 0, 5) : '') }}" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
        @error('end_time') <p class="mt-2 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="status" class="mb-2 block text-sm font-semibold text-[#0F172A]">Estado *</label>
        <select id="status" name="status" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
            @foreach ($statusLabels as $value => $label)
                <option value="{{ $value }}" @selected(old('status', $appointment?->status ?? 'scheduled') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('status') <p class="mt-2 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
    </div>
    <div class="md:col-span-2">
        <label for="reason" class="mb-2 block text-sm font-semibold text-[#0F172A]">Motivo</label>
        <textarea id="reason" name="reason" rows="3" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">{{ old('reason', $appointment?->reason) }}</textarea>
        @error('reason') <p class="mt-2 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
    </div>
    <div class="md:col-span-2">
        <label for="notes" class="mb-2 block text-sm font-semibold text-[#0F172A]">Notas</label>
        <textarea id="notes" name="notes" rows="3" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">{{ old('notes', $appointment?->notes) }}</textarea>
        @error('notes') <p class="mt-2 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
    </div>
    @error('clinic_id') <p class="md:col-span-2 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
</div>
<div class="flex flex-col-reverse gap-3 border-t border-[#E2E8F0] px-5 py-4 sm:flex-row sm:justify-end">
    <a href="{{ route('appointments.index') }}" class="inline-flex items-center justify-center rounded-lg border border-[#E2E8F0] px-4 py-3 text-sm font-semibold text-[#475569]">Cancelar</a>
    <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-[#2563EB] px-4 py-3 text-sm font-semibold text-white shadow-sm shadow-blue-500/20 transition hover:bg-blue-700">{{ $buttonText }}</button>
</div>
