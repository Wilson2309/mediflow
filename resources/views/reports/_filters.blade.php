@php
    $showStatus = $showStatus ?? false;
    $showDoctor = $showDoctor ?? false;
    $showService = $showService ?? false;
    $showPatient = $showPatient ?? false;
    $showSpecialty = $showSpecialty ?? false;
    $showPaymentStatus = $showPaymentStatus ?? false;
    $showPaymentMethod = $showPaymentMethod ?? false;
@endphp

@if ($errors->any())
    <div class="rounded-lg border border-[#EF4444]/20 bg-[#EF4444]/10 px-4 py-3 text-sm text-[#B91C1C]">
        <p class="font-semibold">Revisa los filtros ingresados.</p>
        <ul class="mt-1 list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

<form method="GET" action="{{ route($routeName) }}" class="rounded-lg border border-[#E2E8F0] bg-white p-5 shadow-sm">
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div><label for="start_date" class="mb-2 block text-sm font-semibold text-[#0F172A]">Desde</label><input id="start_date" name="start_date" type="date" value="{{ $startDate }}" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]"></div>
        <div><label for="end_date" class="mb-2 block text-sm font-semibold text-[#0F172A]">Hasta</label><input id="end_date" name="end_date" type="date" value="{{ $endDate }}" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]"></div>

        @if ($showStatus)
            <div><label for="status" class="mb-2 block text-sm font-semibold text-[#0F172A]">Estado</label><select id="status" name="status" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]"><option value="">Todos</option>@foreach ($statusOptions as $value => $label)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></div>
        @endif
        @if ($showDoctor)
            <div><label for="doctor_id" class="mb-2 block text-sm font-semibold text-[#0F172A]">Médico</label><select id="doctor_id" name="doctor_id" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]"><option value="">Todos</option>@foreach ($doctors as $doctor)<option value="{{ $doctor->id }}" @selected((string) ($filters['doctor_id'] ?? '') === (string) $doctor->id)>{{ $doctor->user?->name ?? 'Usuario no asignado' }}</option>@endforeach</select></div>
        @endif
        @if ($showService)
            <div><label for="service_id" class="mb-2 block text-sm font-semibold text-[#0F172A]">Servicio</label><select id="service_id" name="service_id" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]"><option value="">Todos</option>@foreach ($services as $service)<option value="{{ $service->id }}" @selected((string) ($filters['service_id'] ?? '') === (string) $service->id)>{{ $service->name }}</option>@endforeach</select></div>
        @endif
        @if ($showPatient)
            <div><label for="patient_id" class="mb-2 block text-sm font-semibold text-[#0F172A]">Paciente</label><select id="patient_id" name="patient_id" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]"><option value="">Todos</option>@foreach ($patientsList as $patient)<option value="{{ $patient->id }}" @selected((string) ($filters['patient_id'] ?? '') === (string) $patient->id)>{{ $patient->full_name }}</option>@endforeach</select></div>
        @endif
        @if ($showSpecialty)
            <div><label for="specialty_id" class="mb-2 block text-sm font-semibold text-[#0F172A]">Especialidad</label><select id="specialty_id" name="specialty_id" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]"><option value="">Todas</option>@foreach ($specialties as $specialty)<option value="{{ $specialty->id }}" @selected((string) ($filters['specialty_id'] ?? '') === (string) $specialty->id)>{{ $specialty->name }}</option>@endforeach</select></div>
        @endif
        @if ($showPaymentStatus)
            <div><label for="payment_status" class="mb-2 block text-sm font-semibold text-[#0F172A]">Estado de pago</label><select id="payment_status" name="payment_status" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]"><option value="">Todos</option>@foreach (['pending' => 'Pendiente', 'paid' => 'Pagado', 'cancelled' => 'Cancelado', 'refunded' => 'Reembolsado'] as $value => $label)<option value="{{ $value }}" @selected(($filters['payment_status'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></div>
        @endif
        @if ($showPaymentMethod)
            <div><label for="payment_method" class="mb-2 block text-sm font-semibold text-[#0F172A]">Método</label><select id="payment_method" name="payment_method" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]"><option value="">Todos</option>@foreach (['cash' => 'Efectivo', 'card' => 'Tarjeta', 'transfer' => 'Transferencia', 'other' => 'Otro'] as $value => $label)<option value="{{ $value }}" @selected(($filters['payment_method'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></div>
        @endif
    </div>
    <div class="mt-5 flex flex-wrap justify-end gap-2"><a href="{{ route($routeName) }}" class="rounded-lg border border-[#E2E8F0] px-4 py-2.5 text-sm font-semibold text-[#475569]">Restablecer</a><button type="submit" class="rounded-lg bg-[#0F172A] px-4 py-2.5 text-sm font-semibold text-white">Aplicar filtros</button></div>
</form>
