@php
    $statusClasses = [
        'pending' => 'border-[#F59E0B]/20 bg-[#F59E0B]/10 text-[#B45309]',
        'contacted' => 'border-[#2563EB]/20 bg-[#2563EB]/10 text-[#2563EB]',
        'converted' => 'border-[#10B981]/20 bg-[#10B981]/10 text-[#047857]',
        'discarded' => 'border-slate-200 bg-slate-100 text-[#475569]',
    ];
@endphp

<x-app-layout>
    <div class="space-y-6">
        <section class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-[#2563EB]">Solicitud de demo</p>
                <h1 class="mt-2 text-2xl font-bold tracking-tight text-[#0F172A] sm:text-3xl">{{ $demoRequest->full_name }}</h1>
                <p class="mt-2 text-sm text-[#475569]">Recibida el {{ $demoRequest->created_at?->format('d/m/Y \a \l\a\s H:i') }}</p>
            </div>
            <a href="{{ route('demo-requests.index') }}" class="inline-flex items-center justify-center rounded-lg border border-[#E2E8F0] bg-white px-4 py-2.5 text-sm font-semibold text-[#475569] transition hover:border-[#2563EB] hover:text-[#2563EB]">Volver al listado</a>
        </section>

        @if (session('success'))
            <div class="rounded-lg border border-[#10B981]/20 bg-[#10B981]/10 px-4 py-3 text-sm font-semibold text-[#047857]">{{ session('success') }}</div>
        @endif

        <div class="grid gap-6 xl:grid-cols-[1.15fr_.85fr]">
            <section class="rounded-lg border border-[#E2E8F0] bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between gap-4 border-b border-[#E2E8F0] pb-5">
                    <div><h2 class="text-lg font-bold text-[#0F172A]">Datos del prospecto</h2><p class="mt-1 text-sm text-[#475569]">Información enviada desde la landing pública.</p></div>
                    <span class="inline-flex rounded-full border px-3 py-1 text-xs font-bold {{ $statusClasses[$demoRequest->status] ?? $statusClasses['pending'] }}">{{ \App\Models\DemoRequest::STATUSES[$demoRequest->status] ?? $demoRequest->status }}</span>
                </div>

                <dl class="mt-6 grid gap-5 sm:grid-cols-2">
                    <div><dt class="text-xs font-bold uppercase tracking-wide text-[#475569]">Correo</dt><dd class="mt-1 text-sm font-semibold text-[#0F172A]"><a class="text-[#2563EB] hover:underline" href="mailto:{{ $demoRequest->email }}">{{ $demoRequest->email }}</a></dd></div>
                    <div><dt class="text-xs font-bold uppercase tracking-wide text-[#475569]">Teléfono</dt><dd class="mt-1 text-sm font-semibold text-[#0F172A]">{{ $demoRequest->phone ?: 'No especificado' }}</dd></div>
                    <div><dt class="text-xs font-bold uppercase tracking-wide text-[#475569]">Tipo de consultorio</dt><dd class="mt-1 text-sm font-semibold text-[#0F172A]">{{ \App\Models\DemoRequest::CLINIC_TYPES[$demoRequest->clinic_type] ?? 'No especificado' }}</dd></div>
                    <div><dt class="text-xs font-bold uppercase tracking-wide text-[#475569]">Cantidad de médicos</dt><dd class="mt-1 text-sm font-semibold text-[#0F172A]">{{ \App\Models\DemoRequest::DOCTORS_COUNTS[$demoRequest->doctors_count] ?? 'No especificado' }}</dd></div>
                    <div><dt class="text-xs font-bold uppercase tracking-wide text-[#475569]">Módulo de interés</dt><dd class="mt-1 text-sm font-semibold text-[#0F172A]">{{ \App\Models\DemoRequest::INTEREST_MODULES[$demoRequest->interest_module] ?? 'Interés general' }}</dd></div>
                    <div><dt class="text-xs font-bold uppercase tracking-wide text-[#475569]">Origen</dt><dd class="mt-1 text-sm font-semibold text-[#0F172A]">{{ $demoRequest->source ?: 'No especificado' }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-xs font-bold uppercase tracking-wide text-[#475569]">Mensaje</dt><dd class="mt-2 whitespace-pre-line rounded-lg bg-[#F8FAFC] p-4 text-sm leading-6 text-[#475569]">{{ $demoRequest->message ?: 'Sin mensaje adicional.' }}</dd></div>
                </dl>

                <div class="mt-6 border-t border-[#E2E8F0] pt-5 text-xs text-slate-400">
                    <p>IP: {{ $demoRequest->ip_address ?: 'No disponible' }}</p>
                    <p class="mt-1 break-all">Agente: {{ $demoRequest->user_agent ?: 'No disponible' }}</p>
                    @if ($demoRequest->contacted_at)<p class="mt-1 font-semibold text-[#2563EB]">Primer contacto registrado: {{ $demoRequest->contacted_at->format('d/m/Y H:i') }}</p>@endif
                </div>
            </section>

            <section class="rounded-lg border border-[#E2E8F0] bg-white p-6 shadow-sm">
                <h2 class="text-lg font-bold text-[#0F172A]">Seguimiento</h2>
                <p class="mt-1 text-sm text-[#475569]">Actualiza el estado comercial y conserva notas internas.</p>

                @can('demo_requests.update')
                    <form method="POST" action="{{ route('demo-requests.update', $demoRequest) }}" class="mt-6 space-y-5">
                        @csrf
                        @method('PATCH')
                        <div>
                            <label for="status" class="mb-2 block text-sm font-semibold text-[#0F172A]">Estado</label>
                            <select id="status" name="status" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
                                @foreach (\App\Models\DemoRequest::STATUSES as $value => $label)<option value="{{ $value }}" @selected(old('status', $demoRequest->status) === $value)>{{ $label }}</option>@endforeach
                            </select>
                            @error('status')<p class="mt-2 text-sm font-semibold text-[#EF4444]">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="notes" class="mb-2 block text-sm font-semibold text-[#0F172A]">Notas internas</label>
                            <textarea id="notes" name="notes" rows="8" maxlength="5000" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]" placeholder="Acuerdos, próximos pasos o resultado del contacto">{{ old('notes', $demoRequest->notes) }}</textarea>
                            @error('notes')<p class="mt-2 text-sm font-semibold text-[#EF4444]">{{ $message }}</p>@enderror
                        </div>
                        <button type="submit" class="inline-flex w-full items-center justify-center rounded-lg bg-[#2563EB] px-4 py-3 text-sm font-semibold text-white shadow-sm shadow-blue-500/20 transition hover:bg-blue-700">Guardar seguimiento</button>
                    </form>
                @else
                    <div class="mt-6 rounded-lg bg-[#F8FAFC] p-4"><p class="text-xs font-bold uppercase tracking-wide text-[#475569]">Notas internas</p><p class="mt-2 whitespace-pre-line text-sm leading-6 text-[#475569]">{{ $demoRequest->notes ?: 'Sin notas registradas.' }}</p></div>
                @endcan
            </section>
        </div>
    </div>
</x-app-layout>
