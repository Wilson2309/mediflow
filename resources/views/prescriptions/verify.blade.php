<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verificación de receta - MediFlow</title>
    <link rel="icon" type="image/png" href="{{ asset('brand/favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('brand/favicon.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#F8FAFC] font-sans text-[#0F172A] antialiased">
    <main class="mx-auto flex min-h-screen max-w-3xl flex-col justify-center px-4 py-10">
        <section class="rounded-lg border border-[#E2E8F0] bg-white p-6 shadow-sm sm:p-8">
            <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <img src="{{ asset('brand/mediflow-logo-primary.png') }}" alt="MediFlow" class="h-12 w-auto">
                    <p class="mt-4 text-sm font-semibold uppercase tracking-wide text-[#2563EB]">Verificación pública</p>
                    <h1 class="mt-2 text-2xl font-bold text-[#0F172A]">Firma electrónica de receta</h1>
                </div>
                @if ($status === 'valid')
                    <span class="inline-flex w-fit rounded-full border border-[#10B981]/20 bg-[#10B981]/10 px-3 py-1 text-xs font-bold text-[#10B981]">Documento válido</span>
                @elseif ($status === 'altered')
                    <span class="inline-flex w-fit rounded-full border border-[#EF4444]/20 bg-[#EF4444]/10 px-3 py-1 text-xs font-bold text-[#EF4444]">Documento alterado</span>
                @else
                    <span class="inline-flex w-fit rounded-full border border-[#F59E0B]/20 bg-[#F59E0B]/10 px-3 py-1 text-xs font-bold text-[#B45309]">Código no encontrado</span>
                @endif
            </div>

            @if ($status === 'not_found')
                <div class="mt-6 rounded-lg border border-[#F59E0B]/20 bg-[#F59E0B]/10 px-4 py-3 text-sm font-semibold text-[#B45309]">
                    Código no encontrado. Revise que el código de verificación esté escrito correctamente.
                </div>
                <dl class="mt-6 text-sm">
                    <div><dt class="font-semibold text-[#475569]">Código consultado</dt><dd class="mt-1 font-mono text-[#0F172A]">{{ $code }}</dd></div>
                </dl>
            @else
                @if ($status === 'valid')
                    <div class="mt-6 rounded-lg border border-[#10B981]/20 bg-[#10B981]/10 px-4 py-3 text-sm font-semibold text-[#047857]">
                        Documento válido. La información actual coincide con la firma registrada en MediFlow.
                    </div>
                @else
                    <div class="mt-6 rounded-lg border border-[#EF4444]/20 bg-[#EF4444]/10 px-4 py-3 text-sm font-semibold text-[#B91C1C]">
                        Advertencia: los datos de la receta no coinciden con la firma registrada.
                    </div>
                @endif

                <dl class="mt-6 grid gap-4 text-sm sm:grid-cols-2">
                    <div><dt class="font-semibold text-[#475569]">Código de verificación</dt><dd class="mt-1 font-mono text-[#0F172A]">{{ $prescription->signature_verification_code }}</dd></div>
                    <div><dt class="font-semibold text-[#475569]">Receta No.</dt><dd class="mt-1 text-[#0F172A]">REC-{{ str_pad((string) $prescription->id, 6, '0', STR_PAD_LEFT) }}</dd></div>
                    <div><dt class="font-semibold text-[#475569]">Fecha de firma</dt><dd class="mt-1 text-[#0F172A]">{{ $prescription->signed_at?->format('d/m/Y H:i') ?: 'Sin registro' }}</dd></div>
                    <div><dt class="font-semibold text-[#475569]">Médico firmante</dt><dd class="mt-1 text-[#0F172A]">{{ $prescription->doctor?->user?->name ?: 'No disponible' }}</dd></div>
                    <div><dt class="font-semibold text-[#475569]">Licencia del médico</dt><dd class="mt-1 text-[#0F172A]">{{ $prescription->doctor?->license_number ?: 'No registrada' }}</dd></div>
                    <div><dt class="font-semibold text-[#475569]">Clínica</dt><dd class="mt-1 text-[#0F172A]">{{ $prescription->patient?->clinic?->name ?: 'No disponible' }}</dd></div>
                    <div><dt class="font-semibold text-[#475569]">Estado de receta</dt><dd class="mt-1 text-[#0F172A]">{{ $prescription->status === 'active' ? 'Activa' : 'Cancelada' }}</dd></div>
                    <div><dt class="font-semibold text-[#475569]">Firmado por usuario</dt><dd class="mt-1 text-[#0F172A]">{{ $prescription->signedByUser?->name ?: 'No disponible' }}</dd></div>
                    <div><dt class="font-semibold text-[#475569]">Paciente</dt><dd class="mt-1 text-[#0F172A]">{{ $patientName }}</dd></div>
                    <div><dt class="font-semibold text-[#475569]">Identificación</dt><dd class="mt-1 text-[#0F172A]">{{ $maskedIdentification }}</dd></div>
                    <div class="sm:col-span-2"><dt class="font-semibold text-[#475569]">Hash registrado</dt><dd class="mt-1 break-all font-mono text-xs text-[#0F172A]">{{ substr((string) $prescription->signature_hash, 0, 24) }}...</dd></div>
                    <div class="sm:col-span-2"><dt class="font-semibold text-[#475569]">Hash actual</dt><dd class="mt-1 break-all font-mono text-xs text-[#0F172A]">{{ substr((string) $currentHash, 0, 24) }}...</dd></div>
                </dl>
            @endif
        </section>
    </main>
</body>
</html>