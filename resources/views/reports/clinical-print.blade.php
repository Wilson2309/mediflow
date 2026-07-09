@php
    $timezone = config('app.timezone', 'America/Guayaquil');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte clínico</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #F8FAFC; color: #0F172A; font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; }
        .page { max-width: 100%; margin: 0 auto; padding: 18px; }
        .sheet { background: #FFFFFF; border: 1px solid #E2E8F0; padding: 18px; }
        .header { display: table; width: 100%; border-bottom: 2px solid #2563EB; padding-bottom: 12px; margin-bottom: 14px; }
        .brand, .meta { display: table-cell; vertical-align: top; }
        .meta { text-align: right; color: #475569; }
        h1 { margin: 4px 0 6px; font-size: 21px; }
        h2 { margin: 0 0 8px; font-size: 13px; }
        .muted { color: #475569; }
        .grid { display: table; width: 100%; border-spacing: 6px; margin: 0 -6px 14px; }
        .row { display: table-row; }
        .card { display: table-cell; width: 25%; border: 1px solid #E2E8F0; padding: 8px; vertical-align: top; }
        .label { color: #475569; font-size: 9px; text-transform: uppercase; }
        .value { margin-top: 4px; font-size: 15px; font-weight: 700; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #F8FAFC; color: #475569; font-size: 7.5px; text-align: left; text-transform: uppercase; }
        th, td { border-bottom: 1px solid #E2E8F0; padding: 4px; }
        .right { text-align: right; }
        .consultations-table { table-layout: fixed; font-size: 8.5px; line-height: 1.25; }
        .consultations-table th, .consultations-table td { overflow-wrap: break-word; word-wrap: break-word; white-space: normal; vertical-align: top; }
        .col-number { width: 9%; }
        .col-date { width: 12%; }
        .col-patient { width: 18%; }
        .col-doctor { width: 16%; }
        .col-diagnosis { width: 20%; }
        .col-treatment { width: 25%; }
        .actions { margin: 0 auto 12px; max-width: 1120px; padding: 16px 24px 0; text-align: right; }
        .button { border: 0; border-radius: 8px; background: #0F172A; color: #FFFFFF; cursor: pointer; font-weight: 700; padding: 10px 14px; }
        .note { margin-top: 16px; border-top: 1px solid #E2E8F0; padding-top: 10px; color: #475569; font-size: 10px; }
        @page { size: A4 landscape; margin: 10mm 8mm; }
        @media print {
            body { background: #FFFFFF; }
            .actions { display: none; }
            .page { max-width: none; padding: 0; }
            .sheet { border: 0; padding: 0; }
        }
    </style>
</head>
<body>
@if (! $forPdf)
    <div class="actions"><button class="button" type="button" onclick="window.print()">Imprimir</button></div>
@endif
<div class="page">
    <main class="sheet">
        <section class="header">
            <div class="brand">
                @if($clinic && $clinic->logo_path)
                    <img src="{{ asset('storage/' . $clinic->logo_path) }}" alt="{{ $clinic->name }}" style="max-height: 50px; width: auto; margin-bottom: 8px;">
                @else
                    <p class="label">{{ $clinic?->name ?? 'Consultorio' }}</p>
                @endif
                <h1>Reporte clínico</h1>
            </div>
            <div class="meta">
                <p><strong>Periodo:</strong> {{ $periodLabel }}</p>
                <p><strong>Generado:</strong> {{ $generatedAt->timezone($timezone)->format('d/m/Y H:i') }}</p>
                <p><strong>Usuario:</strong> {{ $generatedBy?->name ?? 'Sistema' }}</p>
            </div>
        </section>

        <section class="grid">
            <div class="row">
                <div class="card"><div class="label">Consultas</div><div class="value">{{ number_format($metrics['consultations'] ?? 0) }}</div></div>
                <div class="card"><div class="label">Recetas emitidas</div><div class="value">{{ number_format($metrics['prescriptions'] ?? 0) }}</div></div>
                <div class="card"><div class="label">Con historial clínico</div><div class="value">{{ number_format($metrics['withMedicalRecord'] ?? 0) }}</div></div>
                <div class="card"><div class="label">Sin historial clínico</div><div class="value">{{ number_format($metrics['withoutMedicalRecord'] ?? 0) }}</div></div>
            </div>
        </section>

        <section>
            <h2>Consultas del periodo</h2>
            <table class="consultations-table">
                <thead><tr><th class="col-number">Nro. consulta</th><th class="col-date">Fecha</th><th class="col-patient">Paciente</th><th class="col-doctor">Médico</th><th class="col-diagnosis">Diagnóstico</th><th class="col-treatment">Tratamiento</th></tr></thead>
                <tbody>
                    @forelse ($consultationsList as $consultation)
                        <tr>
                            <td>{{ 'CON-'.str_pad((string) $consultation->id, 6, '0', STR_PAD_LEFT) }}</td>
                            <td>{{ $consultation->consultation_date?->format('d/m/Y H:i') ?? 'Sin fecha' }}</td>
                            <td>{{ $consultation->patient?->full_name ?? 'Sin paciente' }}</td>
                            <td>{{ $consultation->doctor?->user?->name ?? 'Sin médico' }}</td>
                            <td>{{ $consultation->diagnosis ?: 'Sin diagnóstico' }}</td>
                            <td>{{ $consultation->treatment ?: 'Sin tratamiento' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="muted">No hay consultas para los filtros seleccionados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        <p class="note">Reporte de control interno. La información clínica respeta el consultorio autenticado, los filtros aplicados y la zona horaria {{ $timezone }}.</p>
        <div style="margin-top: 20px; text-align: center; font-size: 10px; color: #94A3B8;">
            Generado de forma segura por <strong>MediFlow</strong>
        </div>
    </main>
</div>
</body>
</html>
