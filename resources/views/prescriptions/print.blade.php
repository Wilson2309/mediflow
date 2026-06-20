@php
    $patient = $prescription->patient;
    $doctor = $prescription->doctor;
    $consultation = $prescription->consultation;
    $recordCode = 'REC-'.str_pad((string) $prescription->id, 6, '0', STR_PAD_LEFT);
    $forPdf = $forPdf ?? false;
    $signatureQrCode = $signatureQrCode ?? null;
    $logoSrc = $forPdf ? public_path('brand/mediflow-logo-primary-pdf.png') : asset('brand/mediflow-logo-primary-pdf.png');
    $patientAge = $patient?->birth_date ? $patient->birth_date->age.' años' : '-';
    $patientGender = $patient?->gender ? str($patient->gender)->replace('_', ' ')->title() : '-';
    $doctorName = $doctor?->user?->name ?: '-';
    $specialtyName = $doctor?->specialty?->name ?: '-';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Receta médica {{ $recordCode }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: #fff;
            color: #0f172a;
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 10.5px;
            line-height: 1.28;
        }
        .toolbar { max-width: 820px; margin: 14px auto; text-align: right; }
        .button { border: 1px solid #cbd5e1; border-radius: 6px; color: #0f172a; display: inline-block; font-weight: 700; margin-left: 8px; padding: 8px 12px; text-decoration: none; }
        .button-primary { background: #2563eb; border-color: #2563eb; color: #fff; }
        .page { background: #fff; padding: 16px 20px; }
        .header-table, .info-table, .medicine-table, .clinical-table, .footer-table, .verification-table { border-collapse: collapse; width: 100%; }
        .header-table td { vertical-align: top; }
        .logo { height: 45px; width: auto; }
        .clinic-info { color: #334155; font-size: 9.5px; line-height: 1.28; text-align: right; }
        .clinic-name { color: #0f172a; font-size: 12.5px; font-weight: 700; margin-bottom: 3px; }
        .divider { border-top: 2px solid #0f172a; margin: 8px 0 9px; }
        .title { color: #0f172a; font-size: 16px; font-weight: 800; letter-spacing: .08em; margin: 8px 0 8px; text-align: center; }
        .section-title { color: #2563eb; font-size: 9.5px; font-weight: 800; letter-spacing: .05em; margin: 8px 0 4px; text-transform: uppercase; }
        .info-table th, .info-table td, .medicine-table th, .medicine-table td, .clinical-table th, .clinical-table td { border: 1px solid #cbd5e1; padding: 4px 5px; vertical-align: top; }
        .info-table th, .medicine-table th, .clinical-table th { background: #f1f5f9; color: #334155; font-size: 8.5px; font-weight: 800; text-align: left; text-transform: uppercase; }
        .info-table td, .medicine-table td, .clinical-table td { font-size: 9.5px; }
        .instructions { border: 1px solid #cbd5e1; padding: 6px 7px; white-space: pre-line; }
        .verification-box { border: 1px solid #cbd5e1; margin-top: 9px; padding: 8px; }
        .verification-title { color: #0f172a; font-size: 10px; font-weight: 800; margin-bottom: 4px; }
        .verification-table td { vertical-align: top; }
        .verification-copy { color: #334155; font-size: 9px; line-height: 1.32; padding-right: 10px; }
        .verification-code { color: #0f172a; font-family: DejaVu Sans Mono, monospace; font-size: 9px; font-weight: 800; }
        .qr-cell { text-align: center; width: 102px; }
        .qr-code { height: 86px; width: 86px; }
        .unsigned-box { background: #fffbeb; border: 1px solid #fde68a; color: #92400e; font-size: 9.5px; font-weight: 700; margin-top: 8px; padding: 7px 8px; }
        .footer-table { border-top: 1px solid #cbd5e1; color: #64748b; font-size: 8.5px; margin-top: 12px; }
        .footer-table td { padding-top: 5px; }
        .right { text-align: right; }
        @media print { .toolbar { display: none; } }
    </style>
</head>
<body>
    @unless ($forPdf)
        <div class="toolbar">
            <a class="button" href="{{ route('prescriptions.show', $prescription) }}">Volver</a>
            <button class="button button-primary" onclick="window.print()">Imprimir</button>
        </div>
    @endunless

    <div class="page">
        <table class="header-table">
            <tr>
                <td style="width: 42%;">
                    <img class="logo" src="{{ $logoSrc }}" alt="MediFlow">
                </td>
                <td class="clinic-info">
                    <div class="clinic-name">{{ $clinic?->name ?? 'Consultorio' }}</div>
                    <div>RUC: {{ $clinic?->ruc ?: '-' }}</div>
                    <div>Teléfono: {{ $clinic?->phone ?: '-' }}</div>
                    <div>Email: {{ $clinic?->email ?: '-' }}</div>
                    <div>Dirección: {{ $clinic?->address ?: '-' }}</div>
                </td>
            </tr>
        </table>

        <div class="divider"></div>
        <div class="title">RECETA MÉDICA</div>

        <table class="info-table">
            <tr>
                <th>No. de receta</th>
                <th>Fecha</th>
                <th>Paciente</th>
                <th>Identificación</th>
            </tr>
            <tr>
                <td>{{ $recordCode }}</td>
                <td>{{ $prescription->prescription_date?->format('d/m/Y') ?: '-' }}</td>
                <td>{{ $patient?->full_name ?: '-' }}</td>
                <td>{{ $patient?->identification_number ?: '-' }}</td>
            </tr>
            <tr>
                <th>Género</th>
                <th>Edad</th>
                <th>Teléfono</th>
                <th>Médico</th>
            </tr>
            <tr>
                <td>{{ $patientGender }}</td>
                <td>{{ $patientAge }}</td>
                <td>{{ $patient?->phone ?: '-' }}</td>
                <td>{{ $doctorName }}</td>
            </tr>
            <tr>
                <th>Especialidad</th>
                <th>Registro/licencia</th>
                <th>Alergias</th>
                <th>Correo paciente</th>
            </tr>
            <tr>
                <td>{{ $specialtyName }}</td>
                <td>{{ $doctor?->license_number ?: '-' }}</td>
                <td>{{ $patient?->allergies ?: '-' }}</td>
                <td>{{ $patient?->email ?: '-' }}</td>
            </tr>
        </table>

        <div class="section-title">Medicamentos e indicaciones</div>
        <table class="medicine-table">
            <thead>
                <tr>
                    <th style="width: 26%;">Medicamento</th>
                    <th style="width: 14%;">Dosis</th>
                    <th style="width: 18%;">Frecuencia</th>
                    <th style="width: 14%;">Duración</th>
                    <th style="width: 28%;">Indicaciones</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($prescription->items as $item)
                    <tr>
                        <td>{{ $item->medication_name ?: '-' }}</td>
                        <td>{{ $item->dosage ?: '-' }}</td>
                        <td>{{ $item->frequency ?: '-' }}</td>
                        <td>{{ $item->duration ?: '-' }}</td>
                        <td>{{ $item->instructions ?: '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="section-title">Indicaciones generales</div>
        <div class="instructions">{{ $prescription->general_instructions ?: '-' }}</div>

        @if ($consultation && ($consultation->diagnosis || $consultation->reason || $consultation->observations))
            <div class="section-title">Diagnóstico / consulta</div>
            <table class="clinical-table">
                @if ($consultation->diagnosis)
                    <tr>
                        <th style="width: 18%;">Dx</th>
                        <td>{{ $consultation->diagnosis }}</td>
                    </tr>
                @endif
                @if ($consultation->reason)
                    <tr>
                        <th>Motivo</th>
                        <td>{{ $consultation->reason }}</td>
                    </tr>
                @endif
                @if ($consultation->observations)
                    <tr>
                        <th>Observaciones</th>
                        <td>{{ $consultation->observations }}</td>
                    </tr>
                @endif
            </table>
        @endif
        <div class="section-title">Firma electrónica interna</div>
        @if ($prescription->isSigned())
            <div class="verification-box">
                <table class="verification-table">
                    <tr>
                        <td class="verification-copy">
                            <div class="verification-title">Firmado electrónicamente en MediFlow</div>
                            <div>Dr./Dra. {{ $doctorName }}</div>
                            <div>Licencia profesional: {{ $doctor?->license_number ?: '-' }}</div>
                            <div>Fecha de firma: {{ $prescription->signed_at?->format('d/m/Y H:i') ?: '-' }}</div>
                            <div>Código de verificación: <span class="verification-code">{{ $prescription->signature_verification_code }}</span></div>
                            <div>Firmado por usuario: {{ $prescription->signedByUser?->name ?: '-' }}</div>
                        </td>
                        <td class="qr-cell">
                            @if ($signatureQrCode)
                                <img class="qr-code" src="{{ $signatureQrCode }}" alt="QR de verificación">
                            @endif
                            <div style="font-size: 8px; color: #475569;">Escanee este código para verificar la autenticidad del documento.</div>
                        </td>
                    </tr>
                </table>
            </div>
        @else
            <div class="unsigned-box">Documento no firmado electrónicamente.</div>
        @endif

        <table class="footer-table">
            <tr>
                <td>Documento generado por MediFlow</td>
                <td class="right">{{ $generatedAt->format('d/m/Y H:i') }}</td>
            </tr>
        </table>
    </div>
</body>
</html>
