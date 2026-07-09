@php
    $timezone = config('app.timezone', 'America/Guayaquil');
    $statusOptions = ['scheduled' => 'Programada', 'confirmed' => 'Confirmada', 'completed' => 'Completada', 'cancelled' => 'Cancelada', 'no_show' => 'No asistió'];
    $statusClasses = ['completed' => '#047857', 'confirmed' => '#047857', 'scheduled' => '#2563EB', 'cancelled' => '#EF4444', 'no_show' => '#475569'];
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte de citas</title>
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
        .card { display: table-cell; width: 20%; border: 1px solid #E2E8F0; padding: 8px; vertical-align: top; }
        .label { color: #475569; font-size: 9px; text-transform: uppercase; }
        .value { margin-top: 4px; font-size: 15px; font-weight: 700; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #F8FAFC; color: #475569; font-size: 7.5px; text-align: left; text-transform: uppercase; }
        th, td { border-bottom: 1px solid #E2E8F0; padding: 4px; }
        .right { text-align: right; }
        .status { font-weight: 700; }
        .appointments-table { table-layout: fixed; font-size: 8.5px; line-height: 1.25; }
        .appointments-table th, .appointments-table td { overflow-wrap: break-word; word-wrap: break-word; white-space: normal; vertical-align: top; }
        .col-number { width: 9%; }
        .col-date { width: 9%; }
        .col-time { width: 6%; }
        .col-patient { width: 18%; }
        .col-doctor { width: 16%; }
        .col-service { width: 16%; }
        .col-status { width: 9%; }
        .col-reason { width: 17%; }
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
                <h1>Reporte de citas</h1>
            </div>
            <div class="meta">
                <p><strong>Periodo:</strong> {{ $periodLabel }}</p>
                <p><strong>Generado:</strong> {{ $generatedAt->timezone($timezone)->format('d/m/Y H:i') }}</p>
                <p><strong>Usuario:</strong> {{ $generatedBy?->name ?? 'Sistema' }}</p>
            </div>
        </section>

        <section class="grid">
            <div class="row">
                <div class="card"><div class="label">Total citas</div><div class="value">{{ number_format($metrics['total'] ?? 0) }}</div></div>
                <div class="card"><div class="label">Completadas</div><div class="value">{{ number_format($metrics['completed'] ?? 0) }}</div></div>
                <div class="card"><div class="label">Canceladas</div><div class="value">{{ number_format($metrics['cancelled'] ?? 0) }}</div></div>
                <div class="card"><div class="label">No asistió</div><div class="value">{{ number_format($metrics['noShow'] ?? 0) }}</div></div>
                <div class="card"><div class="label">Tasa cancelación</div><div class="value">{{ $metrics['cancellationRate'] ?? 0 }}%</div></div>
            </div>
        </section>

        <section>
            <h2>Citas del periodo</h2>
            <table class="appointments-table">
                <thead><tr><th class="col-number">Nro. cita</th><th class="col-date">Fecha</th><th class="col-time">Hora</th><th class="col-patient">Paciente</th><th class="col-doctor">Médico</th><th class="col-service">Servicio</th><th class="col-status">Estado</th><th class="col-reason">Motivo</th></tr></thead>
                <tbody>
                    @forelse ($appointmentsList as $appointment)
                        <tr>
                            <td>{{ 'CIT-'.str_pad((string) $appointment->id, 6, '0', STR_PAD_LEFT) }}</td>
                            <td>{{ $appointment->appointment_date?->format('d/m/Y') ?? 'Sin fecha' }}</td>
                            <td>{{ substr((string)$appointment->start_time, 0, 5) ?? 'Sin hora' }}</td>
                            <td>{{ $appointment->patient?->full_name ?? 'Sin paciente' }}</td>
                            <td>{{ $appointment->doctor?->user?->name ?? 'Sin médico' }}</td>
                            <td>{{ $appointment->service?->name ?? 'Sin servicio' }}</td>
                            <td class="status" style="color: {{ $statusClasses[$appointment->status] ?? '#475569' }}">{{ $statusOptions[$appointment->status] ?? $appointment->status }}</td>
                            <td>{{ $appointment->reason ?: 'Sin motivo' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="muted">No hay citas para los filtros seleccionados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        <p class="note">Reporte de control interno. La información de las citas respeta el consultorio autenticado, los filtros aplicados y la zona horaria {{ $timezone }}.</p>
        <div style="margin-top: 20px; text-align: center; font-size: 10px; color: #94A3B8;">
            Generado de forma segura por <strong>MediFlow</strong>
        </div>
    </main>
</div>
</body>
</html>
