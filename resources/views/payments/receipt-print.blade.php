@php
    $methodLabels = ['cash' => 'Efectivo', 'card' => 'Tarjeta', 'transfer' => 'Transferencia', 'other' => 'Otro'];
    $statusLabels = ['pending' => 'Pendiente', 'paid' => 'Pagado', 'cancelled' => 'Cancelado', 'refunded' => 'Reembolsado'];
    $appointmentStatusLabels = ['scheduled' => 'Programada', 'confirmed' => 'Confirmada', 'completed' => 'Completada', 'cancelled' => 'Cancelada', 'no_show' => 'No asistio'];
    $timezone = config('app.timezone', 'America/Guayaquil');
    $patient = $payment->patient;
    $appointment = $payment->appointment;
    $service = $payment->service ?: $appointment?->service;
    $doctorName = $appointment?->doctor?->user?->name ?: '-';
    $paymentDate = $payment->payment_date?->timezone($timezone);
    $generatedAt = $generatedAt->timezone($timezone);
    $forPdf = $forPdf ?? false;
    $logoSrc = $forPdf ? public_path('brand/mediflow-logo-primary-pdf.png') : asset('brand/mediflow-logo-primary-pdf.png');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Recibo de pago {{ $receiptNumber }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #f8fafc; color: #0f172a; font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; line-height: 1.35; }
        .toolbar { max-width: 820px; margin: 14px auto; text-align: right; }
        .button { border: 1px solid #cbd5e1; border-radius: 6px; color: #0f172a; display: inline-block; font-weight: 700; margin-left: 8px; padding: 8px 12px; text-decoration: none; }
        .button-primary { background: #2563eb; border-color: #2563eb; color: #fff; }
        .page { background: #fff; margin: 0 auto; max-width: 820px; min-height: 520px; padding: 24px 28px; }
        .header-table, .info-table, .footer-table { border-collapse: collapse; width: 100%; }
        .header-table td { vertical-align: top; }
        .logo { height: 45px; width: auto; }
        .clinic-info { color: #334155; font-size: 9.5px; line-height: 1.3; text-align: right; }
        .clinic-name { color: #0f172a; font-size: 12.5px; font-weight: 700; margin-bottom: 3px; }
        .divider { border-top: 2px solid #0f172a; margin: 12px 0 12px; }
        .title { color: #0f172a; font-size: 18px; font-weight: 800; letter-spacing: .08em; margin: 10px 0 12px; text-align: center; }
        .receipt-summary { border: 1px solid #cbd5e1; margin-bottom: 12px; padding: 10px 12px; }
        .summary-number { color: #475569; font-size: 9px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
        .summary-amount { color: #0f172a; font-size: 22px; font-weight: 800; margin-top: 3px; }
        .status { border: 1px solid #cbd5e1; border-radius: 999px; display: inline-block; font-size: 9px; font-weight: 800; padding: 4px 8px; text-transform: uppercase; }
        .status-paid { background: #ecfdf5; border-color: #a7f3d0; color: #047857; }
        .status-pending { background: #fffbeb; border-color: #fde68a; color: #b45309; }
        .status-other { background: #f1f5f9; color: #475569; }
        .section-title { color: #2563eb; font-size: 9.5px; font-weight: 800; letter-spacing: .05em; margin: 10px 0 5px; text-transform: uppercase; }
        .info-table th, .info-table td { border: 1px solid #cbd5e1; padding: 6px 7px; vertical-align: top; }
        .info-table th { background: #f1f5f9; color: #334155; font-size: 8.5px; font-weight: 800; text-align: left; text-transform: uppercase; }
        .info-table td { font-size: 10px; }
        .note { border: 1px solid #cbd5e1; color: #334155; margin-top: 10px; padding: 8px; white-space: pre-line; }
        .internal-note { background: #f8fafc; border: 1px solid #cbd5e1; color: #475569; margin-top: 12px; padding: 8px; }
        .footer-table { border-top: 1px solid #cbd5e1; color: #64748b; font-size: 8.5px; margin-top: 14px; }
        .footer-table td { padding-top: 6px; }
        .right { text-align: right; }
        @page { margin: 16mm; }
        @media print { body { background: #fff; } .toolbar { display: none; } .page { box-shadow: none; margin: 0; max-width: none; padding: 0; } }
    </style>
</head>
<body>
    @unless ($forPdf)
        <div class="toolbar">
            <a class="button" href="{{ route('payments.show', $payment) }}">Volver</a>
            <a class="button" href="{{ route('payments.receipt', $payment) }}">Descargar PDF</a>
            <button class="button button-primary" onclick="window.print()">Imprimir</button>
        </div>
    @endunless

    <div class="page">
        <table class="header-table"><tr><td style="width: 42%;"><img class="logo" src="{{ $logoSrc }}" alt="MediFlow"></td><td class="clinic-info"><div class="clinic-name">{{ $clinic?->name ?? 'MediFlow' }}</div><div>RUC: {{ $clinic?->ruc ?: '-' }}</div><div>Telefono: {{ $clinic?->phone ?: '-' }}</div><div>Email: {{ $clinic?->email ?: '-' }}</div><div>Direccion: {{ $clinic?->address ?: '-' }}</div></td></tr></table>
        <div class="divider"></div>
        <div class="title">RECIBO DE PAGO</div>
        <div class="receipt-summary"><table class="header-table"><tr><td><div class="summary-number">No. de recibo</div><div style="font-size: 14px; font-weight: 800; margin-top: 3px;">{{ $receiptNumber }}</div></td><td class="right"><span class="status {{ $payment->payment_status === 'paid' ? 'status-paid' : ($payment->payment_status === 'pending' ? 'status-pending' : 'status-other') }}">{{ $statusLabels[$payment->payment_status] ?? $payment->payment_status }}</span><div class="summary-amount">${{ number_format((float) $payment->amount, 2) }}</div></td></tr></table></div>

        <div class="section-title">Datos del pago</div>
        <table class="info-table"><tr><th>No. pago</th><th>Estado</th><th>Metodo de pago</th><th>Fecha de pago</th></tr><tr><td>#{{ $payment->id }}</td><td>{{ $statusLabels[$payment->payment_status] ?? $payment->payment_status }}</td><td>{{ $payment->payment_status === 'pending' ? 'Por definir al cobrar' : ($methodLabels[$payment->payment_method] ?? $payment->payment_method) }}</td><td>{{ $paymentDate?->format('d/m/Y H:i') ?: '-' }}</td></tr></table>

        <div class="section-title">Paciente</div>
        <table class="info-table"><tr><th>Paciente</th><th>Identificacion/cedula</th><th>Telefono</th><th>Correo</th></tr><tr><td>{{ $patient?->full_name ?: '-' }}</td><td>{{ $patient?->identification_number ?: '-' }}</td><td>{{ $patient?->phone ?: '-' }}</td><td>{{ $patient?->email ?: '-' }}</td></tr></table>

        <div class="section-title">Atencion asociada</div>
        <table class="info-table"><tr><th>Servicio</th><th>Medico</th><th>Fecha de cita</th><th>Estado de cita</th></tr><tr><td>{{ $service?->name ?: '-' }}</td><td>{{ $doctorName }}</td><td>{{ $appointment?->appointment_date?->format('d/m/Y') ?: '-' }} {{ $appointment?->start_time ? substr((string) $appointment->start_time, 0, 5) : '' }}</td><td>{{ $appointment ? ($appointmentStatusLabels[$appointment->status] ?? $appointment->status) : '-' }}</td></tr></table>

        <div class="section-title">Generacion</div>
        <table class="info-table"><tr><th>Generado por</th><th>Fecha de generacion</th><th>Monto pagado</th></tr><tr><td>{{ $generatedBy?->name ?: 'Sistema' }}</td><td>{{ $generatedAt->format('d/m/Y H:i') }}</td><td>${{ number_format((float) $payment->amount, 2) }}</td></tr></table>

        @if ($payment->notes)
            <div class="section-title">Observaciones</div>
            <div class="note">{{ $payment->notes }}</div>
        @endif

        <div class="internal-note">Documento generado por MediFlow para control interno del consultorio.</div>
        <table class="footer-table"><tr><td>MediFlow - Control interno de caja</td><td class="right">{{ $generatedAt->format('d/m/Y H:i') }}</td></tr></table>
    </div>
</body>
</html>