<x-app-layout>
    <div class="space-y-6">
        <header class="flex flex-col gap-4 rounded-lg border border-[#E2E8F0] bg-white p-5 shadow-sm lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-[#2563EB]">Ficha de receta</p>
                <div class="mt-2 flex flex-wrap items-center gap-3">
                    <h1 class="text-2xl font-bold tracking-tight text-[#0F172A] sm:text-3xl">{{ $prescription->patient?->full_name }}</h1>
                    @if ($prescription->isSigned())
                        <span class="inline-flex rounded-full border border-[#10B981]/20 bg-[#10B981]/10 px-3 py-1 text-xs font-bold text-[#10B981]">Firmada electrónicamente</span>
                    @endif
                </div>
                <p class="mt-2 text-sm text-[#475569]">{{ $prescription->prescription_date?->format('d/m/Y') }}</p>
            </div>
            <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:justify-end">
                <a href="{{ route('prescriptions.index') }}" class="inline-flex items-center justify-center rounded-lg border border-[#E2E8F0] px-4 py-3 text-sm font-semibold text-[#475569]">Volver</a>
                @can('prescriptions.view')
                    <a href="{{ route('prescriptions.print', $prescription) }}" target="_blank" class="inline-flex items-center justify-center rounded-lg border border-[#E2E8F0] px-4 py-3 text-sm font-semibold text-[#2563EB]">Imprimir receta</a>
                    <a href="{{ route('prescriptions.pdf', $prescription) }}" class="inline-flex items-center justify-center rounded-lg border border-[#E2E8F0] px-4 py-3 text-sm font-semibold text-[#0F172A]">Descargar PDF</a>
                @endcan
                @if (! $prescription->hasSignatureArtifacts())
                    @if ($prescription->status !== 'cancelled')
                        @can('sign', $prescription)
                        <div x-data="{ submitting: false }" x-on:pageshow.window="submitting = false" x-on:submit="if ($event.defaultPrevented) { return; } if (submitting || ! navigator.onLine) { $event.preventDefault(); return; } submitting = true" class="w-full sm:w-auto">
                        <form method="POST" action="{{ route('prescriptions.sign', $prescription) }}" data-requires-online="true" data-offline-block-message="No se puede firmar ni enviar recetas sin conexión." onsubmit="return confirm('¿Firmar electrónicamente esta receta? Después de firmar no se podrá editar.');">
                            @csrf
                            <button type="submit" x-bind:disabled="submitting" x-bind:aria-busy="submitting.toString()" class="inline-flex w-full items-center justify-center rounded-lg bg-[#10B981] px-4 py-3 text-sm font-semibold text-white shadow-sm shadow-emerald-500/20 disabled:cursor-not-allowed disabled:opacity-60">
                                <span x-show="! submitting">Firmar receta</span>
                                <span x-show="submitting" x-cloak>Firmando...</span>
                            </button>
                        </form>
                        </div>
                        @endcan
                        @can('update', $prescription)
                        <a href="{{ route('prescriptions.edit', $prescription) }}" class="inline-flex items-center justify-center rounded-lg bg-[#2563EB] px-4 py-3 text-sm font-semibold text-white shadow-sm shadow-blue-500/20">Editar receta</a>
                        @endcan
                    @else
                        @can('prescriptions.update')
                        <span class="inline-flex items-center justify-center rounded-lg border border-[#EF4444]/20 bg-[#EF4444]/10 px-4 py-3 text-sm font-semibold text-[#EF4444]">Receta cancelada</span>
                        @endcan
                    @endif
                @endif
            </div>
        </header>

        @if (session('success'))
            <div class="rounded-lg border border-[#10B981]/30 bg-[#10B981]/10 px-4 py-3 text-sm font-semibold text-[#047857]">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="rounded-lg border border-[#EF4444]/30 bg-[#EF4444]/10 px-4 py-3 text-sm font-semibold text-[#B91C1C]">{{ session('error') }}</div>
        @endif
        @if ($errors->has('email'))
            <div class="rounded-lg border border-[#EF4444]/30 bg-[#EF4444]/10 px-4 py-3 text-sm font-semibold text-[#B91C1C]">{{ $errors->first('email') }}</div>
        @endif

        <section class="grid gap-5 lg:grid-cols-4">
            <article class="rounded-lg border border-[#E2E8F0] bg-white p-5 shadow-sm">
                <h2 class="text-base font-bold text-[#0F172A]">Información general</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div><dt class="font-semibold text-[#475569]">Código</dt><dd class="mt-1 text-[#0F172A]">REC-{{ str_pad((string) $prescription->id, 6, '0', STR_PAD_LEFT) }}</dd></div>
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
                <p class="mt-1 text-sm text-[#475569]">{{ $prescription->patient?->email ?: 'Sin correo' }}</p>
            </article>
            <article class="rounded-lg border border-[#E2E8F0] bg-white p-5 shadow-sm">
                <h2 class="text-base font-bold text-[#0F172A]">Médico</h2>
                <p class="mt-4 text-sm font-semibold text-[#0F172A]">{{ $prescription->doctor?->user?->name }}</p>
                <p class="mt-1 text-sm text-[#475569]">{{ $prescription->doctor?->specialty?->name ?: 'Sin especialidad' }}</p>
                <p class="mt-1 text-sm text-[#475569]">{{ $prescription->doctor?->license_number ?: 'Sin licencia' }}</p>
            </article>
            <article class="rounded-lg border border-[#E2E8F0] bg-white p-5 shadow-sm">
                <h2 class="text-base font-bold text-[#0F172A]">Entrega</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div><dt class="font-semibold text-[#475569]">Veces impresa/descargada</dt><dd class="mt-1 text-[#0F172A]">{{ number_format((int) $prescription->print_count) }}</dd></div>
                    <div><dt class="font-semibold text-[#475569]">Última impresión/PDF</dt><dd class="mt-1 text-[#0F172A]">{{ $prescription->last_printed_at?->format('d/m/Y H:i') ?: 'Sin registro' }}</dd></div>
                    <div><dt class="font-semibold text-[#475569]">Veces enviada</dt><dd class="mt-1 text-[#0F172A]">{{ number_format((int) $prescription->email_count) }}</dd></div>
                    <div><dt class="font-semibold text-[#475569]">Último correo</dt><dd class="mt-1 text-[#0F172A]">{{ $prescription->last_emailed_at?->format('d/m/Y H:i') ?: 'Sin registro' }}</dd></div>
                    <div><dt class="font-semibold text-[#475569]">Último destinatario</dt><dd class="mt-1 text-[#0F172A]">{{ $prescription->last_emailed_to ?: 'Sin registro' }}</dd></div>
                </dl>
            </article>
        </section>

        <section class="rounded-lg border border-[#E2E8F0] bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 class="text-base font-bold text-[#0F172A]">Firma electrónica interna</h2>
                    <p class="mt-1 text-sm text-[#475569]">Verificación de integridad generada por MediFlow.</p>
                </div>
                @if ($prescription->isSigned())
                    <span class="inline-flex w-fit rounded-full border border-[#10B981]/20 bg-[#10B981]/10 px-3 py-1 text-xs font-bold text-[#10B981]">Firmada electrónicamente</span>
                @else
                    <span class="inline-flex w-fit rounded-full border border-[#F59E0B]/20 bg-[#F59E0B]/10 px-3 py-1 text-xs font-bold text-[#B45309]">No firmada</span>
                @endif
            </div>
            @if ($prescription->isSigned())
                <dl class="mt-4 grid gap-4 text-sm md:grid-cols-4">
                    <div><dt class="font-semibold text-[#475569]">Firmado por</dt><dd class="mt-1 text-[#0F172A]">{{ $prescription->signedByUser?->name ?: 'Usuario no disponible' }}</dd></div>
                    <div><dt class="font-semibold text-[#475569]">Fecha</dt><dd class="mt-1 text-[#0F172A]">{{ $prescription->signed_at?->format('d/m/Y H:i') }}</dd></div>
                    <div><dt class="font-semibold text-[#475569]">Código de verificación</dt><dd class="mt-1 font-mono text-[#0F172A]">{{ $prescription->signature_verification_code }}</dd></div>
                    <div><dt class="font-semibold text-[#475569]">Verificación</dt><dd class="mt-1"><a href="{{ route('prescriptions.verify', $prescription->signature_verification_code) }}" target="_blank" class="font-semibold text-[#2563EB]">Verificar firma</a></dd></div>
                </dl>
            @else
                <p class="mt-4 rounded-lg border border-[#F59E0B]/20 bg-[#F59E0B]/10 px-4 py-3 text-sm font-semibold text-[#B45309]">Documento no firmado electrónicamente.</p>
            @endif
        </section>

        @can('prescriptions.update')
            <section class="rounded-lg border border-[#E2E8F0] bg-white p-5 shadow-sm">
                <h2 class="text-base font-bold text-[#0F172A]">Enviar por correo</h2>
                <form method="POST" action="{{ route('prescriptions.send-email', $prescription) }}" class="mt-4 grid gap-4 md:grid-cols-[1fr_auto] md:items-end" data-requires-online="true" data-offline-block-message="No se puede firmar ni enviar recetas sin conexión.">
                    @csrf
                    <div>
                        <label for="email" class="mb-2 block text-sm font-semibold text-[#0F172A]">Correo destino</label>
                        <input id="email" name="email" type="email" value="{{ old('email', $prescription->patient?->email) }}" placeholder="paciente@correo.com" class="w-full rounded-lg border-[#E2E8F0] bg-[#F8FAFC] text-sm focus:border-[#2563EB] focus:ring-[#2563EB]">
                        <p class="mt-2 text-xs text-[#475569]">Si deja este campo vacío, se usará el correo registrado del paciente.</p>
                    </div>
                    <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-[#0F172A] px-4 py-3 text-sm font-semibold text-white">Enviar por correo</button>
                </form>
            </section>
        @endcan

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
