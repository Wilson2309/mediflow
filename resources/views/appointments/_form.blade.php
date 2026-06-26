@php
    $prefillPatientId = $prefillPatientId ?? null;
    $statusLabels = [
        'scheduled' => 'Programada',
        'confirmed' => 'Confirmada',
        'completed' => 'Completada',
        'cancelled' => 'Cancelada',
        'no_show' => 'No asistiÃ³',
    ];
    $selectedPatientData = $selectedPatient ? [
        'id' => $selectedPatient->id,
        'label' => trim($selectedPatient->full_name),
        'identification' => $selectedPatient->identification_number,
        'contact' => $selectedPatient->phone ?: $selectedPatient->email,
    ] : null;
    $selectedDoctorData = $selectedDoctor ? [
        'id' => $selectedDoctor->id,
        'label' => $selectedDoctor->user?->name ?? 'MÃ©dico sin usuario',
        'specialty' => $selectedDoctor->specialty?->name,
        'license' => $selectedDoctor->license_number,
    ] : null;
@endphp

<div
    class="grid gap-5 p-5 md:grid-cols-2"
    x-data="mediflowAppointmentForm({
        patientSearchUrl: @js(route('appointments.patients.search')),
        doctorSearchUrl: @js(route('appointments.doctors.search')),
        availabilityUrl: @js(route('appointments.availability')),
        appointmentId: @js($appointment?->id),
        selectedPatient: @js($selectedPatientData),
        selectedDoctor: @js($selectedDoctorData),
        patientId: @js((string) old('patient_id', $appointment?->patient_id ?? $prefillPatientId ?? '')),
        doctorId: @js((string) old('doctor_id', $appointment?->doctor_id ?? '')),
        serviceId: @js((string) old('service_id', $appointment?->service_id ?? '')),
        date: @js(old('appointment_date', $appointment?->appointment_date?->format('Y-m-d') ?? '')),
        startTime: @js(old('start_time', $appointment ? substr((string) $appointment->start_time, 0, 5) : '')),
    })"
    x-init="init()"
>
    <div>
        <label for="patient_search" class="mb-2 block text-sm font-semibold text-[#0F172A]">Paciente *</label>
        <input id="patient_search" type="search" x-model="patientQuery" x-on:input.debounce.250ms="searchPatients()" x-on:focus="searchPatients()" placeholder="Buscar por nombre, cÃ©dula, telÃ©fono o correo" autocomplete="off" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
        <input type="hidden" id="patient_id" name="patient_id" x-model="patientId">
        <div x-show="patientResults.length" class="mt-2 max-h-56 overflow-y-auto rounded-lg border border-[#E2E8F0] bg-white shadow-sm" x-cloak>
            <template x-for="patient in patientResults" :key="patient.id">
                <button type="button" x-on:click="selectPatient(patient)" class="block w-full border-b border-[#E2E8F0] px-3 py-2 text-left text-sm last:border-b-0 hover:bg-[#F8FAFC]">
                    <span class="block font-semibold text-[#0F172A]" x-text="patient.label"></span>
                    <span class="block text-xs text-[#475569]" x-text="[patient.identification || 'Sin identificaciÃ³n', patient.contact || 'Sin contacto'].join(' Â· ')"></span>
                </button>
            </template>
        </div>
        <p class="mt-2 text-xs text-[#475569]">Escriba al menos parte del nombre, cÃ©dula, telÃ©fono o correo.</p>
        @error('patient_id') <p class="mt-2 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="service_id" class="mb-2 block text-sm font-semibold text-[#0F172A]">Servicio</label>
        <select id="service_id" name="service_id" x-model="serviceId" x-on:change="handleServiceChange()" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
            <option value="">Sin servicio</option>
            @foreach ($services as $service)
                <option value="{{ $service->id }}">{{ $service->name }} - ${{ number_format((float) $service->price, 2) }} - {{ $service->duration_minutes }} min</option>
            @endforeach
        </select>
        @error('service_id') <p class="mt-2 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="doctor_search" class="mb-2 block text-sm font-semibold text-[#0F172A]">MÃ©dico *</label>
        <input id="doctor_search" type="search" x-model="doctorQuery" x-on:input.debounce.250ms="searchDoctors()" x-on:focus="searchDoctors()" :disabled="!serviceId" placeholder="Buscar por nombre, especialidad o licencia" autocomplete="off" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400 focus:border-[#2563EB] focus:ring-[#2563EB]">
        <input type="hidden" id="doctor_id" name="doctor_id" x-model="doctorId">
        <p x-show="!serviceId" class="mt-2 text-xs text-[#F59E0B]" x-cloak>Seleccione primero un servicio para ver mÃ©dicos compatibles.</p>
        <p x-show="doctorMessage" class="mt-2 text-xs text-[#EF4444]" x-text="doctorMessage" x-cloak></p>
        <div x-show="doctorResults.length" class="mt-2 max-h-56 overflow-y-auto rounded-lg border border-[#E2E8F0] bg-white shadow-sm" x-cloak>
            <template x-for="doctor in doctorResults" :key="doctor.id">
                <button type="button" x-on:click="selectDoctor(doctor)" class="block w-full border-b border-[#E2E8F0] px-3 py-2 text-left text-sm last:border-b-0 hover:bg-[#F8FAFC]">
                    <span class="block font-semibold text-[#0F172A]" x-text="doctor.label"></span>
                    <span class="block text-xs text-[#475569]" x-text="[doctor.specialty || 'Sin especialidad', doctor.license || 'Sin licencia'].join(' Â· ')"></span>
                </button>
            </template>
        </div>
        @error('doctor_id') <p class="mt-2 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="appointment_date" class="mb-2 block text-sm font-semibold text-[#0F172A]">Fecha *</label>
        <input id="appointment_date" name="appointment_date" type="date" x-model="date" x-on:change="loadAvailability()" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
        @error('appointment_date') <p class="mt-2 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="start_time" class="mb-2 block text-sm font-semibold text-[#0F172A]">Hora inicio *</label>
        <select id="start_time" name="start_time" x-model="startTime" x-on:change="syncEndTime()" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
            <option value="">Seleccione una hora</option>
            <template x-for="slot in availableSlots" :key="slot">
                <option :value="slot" x-text="slot"></option>
            </template>
            <option x-show="startTime && !availableSlots.includes(startTime)" :value="startTime" x-text="startTime + ' (seleccionada)'"></option>
        </select>
        <input type="hidden" id="end_time" name="end_time" x-model="endTime">
        <p x-show="availabilityMessage" class="mt-2 text-xs" :class="availableSlots.length ? 'text-[#475569]' : 'text-[#EF4444]'" x-text="availabilityMessage" x-cloak></p>
        <p x-show="duration" class="mt-2 text-xs text-[#475569]" x-text="'DuraciÃ³n estimada: ' + duration + ' minutos'"></p>
        @error('start_time') <p class="mt-2 text-sm text-[#EF4444]">{{ $message }}</p> @enderror
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

<script>
    window.mediflowAppointmentForm = function (config) {
        return {
            patientId: config.patientId || '',
            doctorId: config.doctorId || '',
            serviceId: config.serviceId || '',
            date: config.date || '',
            startTime: config.startTime || '',
            endTime: '',
            duration: null,
            patientQuery: config.selectedPatient ? config.selectedPatient.label : '',
            doctorQuery: config.selectedDoctor ? config.selectedDoctor.label : '',
            patientResults: [],
            doctorResults: [],
            availableSlots: [],
            availabilityMessage: '',
            doctorMessage: '',
            async init() {
                this.syncEndTime();
                if (this.serviceId && this.doctorId && this.date) {
                    await this.loadAvailability();
                }
            },
            async searchPatients() {
                const response = await fetch(config.patientSearchUrl + '?' + new URLSearchParams({ q: this.patientQuery || '' }), { headers: { Accept: 'application/json' } });
                this.patientResults = response.ok ? await response.json() : [];
            },
            selectPatient(patient) {
                this.patientId = String(patient.id);
                this.patientQuery = patient.label;
                this.patientResults = [];
            },
            async searchDoctors() {
                this.doctorMessage = '';
                if (!this.serviceId) {
                    this.doctorResults = [];
                    this.doctorMessage = 'Seleccione primero un servicio para ver mÃ©dicos compatibles.';
                    return;
                }
                const response = await fetch(config.doctorSearchUrl + '?' + new URLSearchParams({ q: this.doctorQuery || '', service_id: this.serviceId }), { headers: { Accept: 'application/json' } });
                this.doctorResults = response.ok ? await response.json() : [];
                if (this.doctorResults.length === 0) {
                    this.doctorMessage = 'No hay mÃ©dicos compatibles con ese servicio.';
                }
            },
            selectDoctor(doctor) {
                this.doctorId = String(doctor.id);
                this.doctorQuery = doctor.label;
                this.doctorResults = [];
                this.doctorMessage = '';
                this.loadAvailability();
            },
            async handleServiceChange() {
                this.availableSlots = [];
                this.availabilityMessage = '';
                if (!this.serviceId) {
                    this.doctorId = '';
                    this.doctorQuery = '';
                    this.doctorMessage = 'Seleccione primero un servicio para ver mÃ©dicos compatibles.';
                    return;
                }
                const previousDoctorId = this.doctorId;
                await this.searchDoctors();
                if (previousDoctorId && !this.doctorResults.some((doctor) => String(doctor.id) === String(previousDoctorId))) {
                    this.doctorId = '';
                    this.doctorQuery = '';
                    this.doctorMessage = 'Este mÃ©dico no ofrece el servicio seleccionado.';
                }
                await this.loadAvailability();
            },
            async loadAvailability() {
                this.availableSlots = [];
                this.availabilityMessage = '';
                if (!this.doctorId || !this.date) {
                    return;
                }
                const params = {
                    doctor_id: this.doctorId,
                    service_id: this.serviceId || '',
                    date: this.date,
                };
                if (config.appointmentId) {
                    params.appointment_id = config.appointmentId;
                }
                const response = await fetch(config.availabilityUrl + '?' + new URLSearchParams(params), { headers: { Accept: 'application/json' } });
                const data = await response.json();
                if (!response.ok) {
                    this.availabilityMessage = data.message || 'No se pudo consultar la disponibilidad.';
                    return;
                }
                this.availableSlots = data.available_slots || [];
                this.duration = data.duration || null;
                this.availabilityMessage = data.message || (this.availableSlots.length ? 'Horarios disponibles cargados.' : 'No hay horarios disponibles para este mÃ©dico en la fecha seleccionada.');
                if (this.startTime && !this.availableSlots.includes(this.startTime)) {
                    this.availabilityMessage = 'El mÃ©dico ya tiene una cita programada en esa hora.';
                }
                this.syncEndTime();
            },
            syncEndTime() {
                if (!this.startTime || !this.duration) {
                    this.endTime = '';
                    return;
                }
                const [hours, minutes] = this.startTime.split(':').map(Number);
                const date = new Date(2000, 0, 1, hours, minutes + Number(this.duration));
                this.endTime = String(date.getHours()).padStart(2, '0') + ':' + String(date.getMinutes()).padStart(2, '0');
            },
        };
    };
</script>
