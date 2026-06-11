<x-app-layout>
    <div class="space-y-6">
        <header class="flex flex-col gap-4 rounded-lg border border-[#E2E8F0] bg-white p-5 shadow-sm lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-[#2563EB]">Ficha de receta</p>
                <h1 class="mt-2 text-2xl font-bold tracking-tight text-[#0F172A] sm:text-3xl">{{ $prescription->patient?->full_name }}</h1>
                <p class="mt-2 text-sm text-[#475569]">{{ $prescription->prescription_date?->format('d/m/Y') }}</p>
            </div>
            <div class="flex flex-col gap-3 sm:flex-row">
                <a href="{{ route('prescriptions.index') }}" class="inline-flex items-center justify-center rounded-lg border border-[#E2E8F0] px-4 py-3 text-sm font-semibold text-[#475569]">Volver</a>
                <button type="button" disabled class="inline-flex cursor-not-allowed items-center justify-center rounded-lg border border-[#E2E8F0] px-4 py-3 text-sm font-semibold text-[#94A3B8]">Exportar PDF próximamente</button>
                <a href="{{ route('prescriptions.edit', $prescription) }}" class="inline-flex items-center justify-center rounded-lg bg-[#2563EB] px-4 py-3 text-sm font-semibold text-white shadow-sm shadow-blue-500/20">Editar receta</a>
            </div>
        </header>

        @if (session('success'))
            <div class="rounded-lg border border-[#10B981]/30 bg-[#10B981]/10 px-4 py-3 text-sm font-semibold text-[#047857]">{{ session('success') }}</div>
        @endif

        <section class="grid gap-5 lg:grid-cols-3">
            <article class="rounded-lg border border-[#E2E8F0] bg-white p-5 shadow-sm">
                <h2 class="text-base font-bold text-[#0F172A]">Información general</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div><dt class="font-semibold text-[#475569]">Fecha</dt><dd class="mt-1 text-[#0F172A]">{{ $prescription->prescription_date?->format('d/m/Y') }}</dd></div>
                    <div><dt class="font-semibold text-[#475569]">Estado</dt><dd class="mt-1"><span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-bold {{ $prescription->status === 'active' ? 'border-[#10B981]/20 bg-[#10B981]/10 text-[#10B981]' : 'border-[#EF4444]/20 bg-[#EF4444]/10 text-[#EF4444]' }}">{{ $prescription->status === 'active' ? 'Activa' : 'Cancelada' }}</span></dd></div>
                    <div><dt class="font-semibold text-[#475569]">Creación</dt><dd class="mt-1 text-[#0F172A]">{{ $prescription->created_at?->format('d/m/Y H:i') }}</dd></div>
                    <div><dt class="font-semibold text-[#475569]">Actualización</dt><dd class="mt-1 text-[#0F172A]">{{ $prescription->updated_at?->format('d/m/Y H:i') }}</dd></div>
                </dl>
            </article>
            <article class="rounded-lg border border-[#E2E8F0] bg-white p-5 shadow-sm">
                <h2 class="text-base font-bold text-[#0F172A]">Paciente</h2>
                <p class="mt-4 text-sm font-semibold text-[#0F172A]">{{ $prescription->patient?->full_name }}</p>
                <p class="mt-1 text-sm text-[#475569]">{{ $prescription->patient?->identification_number ?: 'Sin identificación' }}</p>
                <p class="mt-1 text-sm text-[#475569]">{{ $prescription->patient?->phone ?: 'Sin teléfono' }}</p>
            </article>
            <article class="rounded-lg border border-[#E2E8F0] bg-white p-5 shadow-sm">
                <h2 class="text-base font-bold text-[#0F172A]">Médico</h2>
                <p class="mt-4 text-sm font-semibold text-[#0F172A]">{{ $prescription->doctor?->user?->name }}</p>
                <p class="mt-1 text-sm text-[#475569]">{{ $prescription->doctor?->specialty?->name ?: 'Sin especialidad' }}</p>
                <p class="mt-1 text-sm text-[#475569]">{{ $prescription->doctor?->phone ?: 'Sin teléfono' }}</p>
            </article>
        </section>

        <section class="rounded-lg border border-[#E2E8F0] bg-white p-5 shadow-sm">
            <h2 class="text-base font-bold text-[#0F172A]">Consulta asociada</h2>
            @if ($prescription->consultation)
                <p class="mt-3 text-sm text-[#475569]">{{ $prescription->consultation->consultation_date?->format('d/m/Y H:i') }} · {{ $prescription->consultation->diagnosis ?: 'Sin diagnóstico' }}</p>
            @else
                <p class="mt-3 text-sm text-[#475569]">Receta registrada sin consulta asociada.</p>
            @endif
        </section>

        <section class="rounded-lg border border-[#E2E8F0] bg-white p-5 shadow-sm">
            <h2 class="text-base font-bold text-[#0F172A]">Instrucciones generales</h2>
            <p class="mt-3 whitespace-pre-line text-sm leading-6 text-[#475569]">{{ $prescription->general_instructions ?: 'Sin instrucciones generales.' }}</p>
        </section>

        <section class="overflow-hidden rounded-lg border border-[#E2E8F0] bg-white shadow-sm">
            <div class="border-b border-[#E2E8F0] px-5 py-4">
                <h2 class="text-base font-bold text-[#0F172A]">Medicamentos e indicaciones</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-[#E2E8F0]">
                    <thead class="bg-[#F8FAFC]">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-[#475569]">Medicamento</th>
                            <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-[#475569]">Dosis</th>
                            <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-[#475569]">Frecuencia</th>
                            <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-[#475569]">Duración</th>
                            <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-[#475569]">Instrucciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E2E8F0] bg-white">
                        @foreach ($prescription->items as $item)
                            <tr>
                                <td class="px-5 py-4 text-sm font-semibold text-[#0F172A]">{{ $item->medication_name }}</td>
                                <td class="px-5 py-4 text-sm text-[#475569]">{{ $item->dosage ?: 'No registrada' }}</td>
                                <td class="px-5 py-4 text-sm text-[#475569]">{{ $item->frequency ?: 'No registrada' }}</td>
                                <td class="px-5 py-4 text-sm text-[#475569]">{{ $item->duration ?: 'No registrada' }}</td>
                                <td class="px-5 py-4 text-sm text-[#475569]">{{ $item->instructions ?: 'Sin instrucciones' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-app-layout>
