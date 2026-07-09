@php
    $timezone = config('app.timezone', 'America/Guayaquil');
    $statusClasses = ['pending' => '#B45309', 'paid' => '#047857', 'cancelled' => '#EF4444', 'refunded' => '#475569'];
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte financiero</title>
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
        .status { font-weight: 700; }
        .payments-table { table-layout: fixed; font-size: 8.5px; line-height: 1.25; }
        .payments-table th, .payments-table td { overflow-wrap: break-word; word-wrap: break-word; white-space: normal; vertical-align: top; }
        .col-number { width: 9%; }
        .col-date { width: 12%; }
        .col-patient { width: 18%; }
        .col-service { width: 17%; }
        .col-doctor { width: 16%; }
        .col-method { width: 10%; }
        .col-status { width: 9%; }
        .col-amount { width: 9%; }
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
                @if(isset($clinic) && $clinic->logo_path)
                    <img src="{{ $forPdf ?? false ? storage_path('app/public/' . $clinic->logo_path) : asset('storage/' . $clinic->logo_path) }}" alt="{{ $clinic->name }}" style="max-height: 50px; width: auto; margin-bottom: 8px;">
                @else
                    <p class="label">{{ $clinic?->name ?? 'Consultorio' }}</p>
                @endif
                <h1>Reporte financiero</h1>
                <p class="muted">{{ $clinic?->name ?? 'Consultorio' }}</p>
            </div>
            <div class="meta">
                <p><strong>Periodo:</strong> {{ $periodLabel }}</p>
                <p><strong>Generado:</strong> {{ $generatedAt->timezone($timezone)->format('d/m/Y H:i') }}</p>
                <p><strong>Usuario:</strong> {{ $generatedBy?->name ?? 'Sistema' }}</p>
            </div>
        </section>

        <section class="grid">
            <div class="row">
                <div class="card"><div class="label">Ingresos pagados</div><div class="value">${{ number_format($metrics['paidIncome'], 2) }}</div></div>
                <div class="card"><div class="label">Pagos pagados</div><div class="value">{{ number_format($metrics['paidPayments']) }}</div></div>
                <div class="card"><div class="label">Pagos pendientes</div><div class="value">{{ number_format($metrics['pending']) }}</div></div>
                <div class="card"><div class="label">Monto pendiente</div><div class="value">${{ number_format($metrics['pendingAmount'], 2) }}</div></div>
            </div>
        </section>

        <section style="margin-bottom: 14px;">
            <h2>Totales por metodo de pago</h2>
            <table>
                <thead><tr><th>Metodo</th><th class="right">Pagos</th><th class="right">Monto</th></tr></thead>
                <tbody>
                    @foreach ($methodLabels as $key => $label)
                        @php($row = $methodTotals[$key] ?? null)
                        <tr><td>{{ $label }}</td><td class="right">{{ number_format((int) ($row?->total_count ?? 0)) }}</td><td class="right">${{ number_format((float) ($row?->total_amount ?? 0), 2) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </section>

        <section>
            <h2>Pagos del periodo</h2>
            <table class="payments-table">
                <thead><tr><th class="col-number">Nro. pago</th><th class="col-date">Fecha</th><th class="col-patient">Paciente</th><th class="col-service">Servicio</th><th class="col-doctor">Medico</th><th class="col-method">Metodo</th><th class="col-status">Estado</th><th class="right col-amount">Monto</th></tr></thead>
                <tbody>
                    @forelse ($payments as $payment)
                        <tr>
                            <td>{{ 'PAG-'.str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT) }}</td>
                            <td>{{ $payment->payment_date?->timezone($timezone)->format('d/m/Y H:i') ?? 'Sin fecha' }}</td>
                            <td>{{ $payment->patient?->full_name ?? 'Sin paciente' }}</td>
                            <td>{{ $payment->service?->name ?? 'Sin servicio' }}</td>
                            <td>{{ $payment->appointment?->doctor?->user?->name ?? 'Sin medico' }}</td>
                            <td>{{ $methodLabels[$payment->payment_method] ?? $payment->payment_method }}</td>
                            <td class="status" style="color: {{ $statusClasses[$payment->payment_status] ?? '#475569' }}">{{ $statusLabels[$payment->payment_status] ?? $payment->payment_status }}</td>
                            <td class="right">${{ number_format((float) $payment->amount, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="muted">No hay pagos para los filtros seleccionados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        <p class="note">Reporte de control interno. La informacion financiera respeta el consultorio autenticado, los filtros aplicados y la zona horaria America/Guayaquil.</p>
        <div style="margin-top: 20px; text-align: center; font-size: 10px; color: #94A3B8;">
            Generado de forma segura por <strong>MediFlow</strong>
        </div>
    </main>
</div>
</body>
</html>
