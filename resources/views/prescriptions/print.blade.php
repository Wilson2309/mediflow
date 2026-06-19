@php
    $patient = $prescription->patient;
    $doctor = $prescription->doctor;
    $recordCode = 'REC-'.str_pad((string) $prescription->id, 6, '0', STR_PAD_LEFT);
    $forPdf = $forPdf ?? false;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Receta médica {{ $recordCode }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #f1f5f9; color: #0f172a; font-family: Arial, sans-serif; font-size: 13px; line-height: 1.45; }
        .toolbar { display: flex; justify-content: space-between; gap: 12px; max-width: 900px; margin: 20px auto; }
        .button { border: 1px solid #cbd5e1; border-radius: 8px; color: #0f172a; display: inline-block; font-weight: 700; padding: 10px 14px; text-decoration: none; }
        .button-primary { background: #2563eb; border-color: #2563eb; color: #fff; }
        .page { background: #fff; border: 1px solid #e2e8f0; margin: 0 auto 24px; max-width: 900px; min-height: 1120px; padding: 42px; }
        .header { align-items: flex-start; border-bottom: 2px solid #0f172a; display: flex; justify-content: space-between; gap: 28px; padding-bottom: 18px; }
        .clinic h1 { font-size: 24px; margin: 0 0 8px; }
        .clinic p, .meta p { margin: 2px 0; }
        .meta { text-align: right; }
        .meta strong { display: block; font-size: 18px; margin-bottom: 8px; }
        .title { font-size: 22px; letter-spacing: .04em; margin: 28px 0 18px; text-align: center; text-transform: uppercase; }
        .grid { display: grid; gap: 18px; grid-template-columns: 1fr 1fr; }
        .box { border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px; }
        .box h2 { font-size: 14px; margin: 0 0 12px; text-transform: uppercase; }
        .row { margin: 4px 0; }
        .label { color: #475569; font-weight: 700; }
        table { border-collapse: collapse; margin-top: 16px; width: 100%; }
        th { background: #f8fafc; color: #475569; font-size: 11px; text-align: left; text-transform: uppercase; }
        th, td { border: 1px solid #e2e8f0; padding: 9px; vertical-align: top; }
        .section { margin-top: 20px; }
        .instructions { white-space: pre-line; }
        .signature { display: flex; justify-content: flex-end; margin-top: 70px; }
        .signature-box { border-top: 1px solid #0f172a; min-width: 260px; padding-top: 10px; text-align: center; }
        .footer { border-top: 1px solid #e2e8f0; color: #475569; display: flex; justify-content: space-between; margin-top: 44px; padding-top: 14px; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .page { border: 0; margin: 0; max-width: none; min-height: auto; padding: 22mm 18mm; }
        }
    </style>
</head>
<body>
    @unless ($forPdf)
        <div class="toolbar">
            <a class="button" href="{{ route('prescriptions.show', $prescription) }}">Volver</a>
            <button class="button button-primary" onclick="window.print()">Imprimir</button>
        </div>
    @endunless

    <main class="page">
        <header class="header">
            <div class="clinic">
                <h1>{{ $clinic?->name ?? 'Consultorio' }}</h1>
                <p><span class="label">RUC:</span> {{ $clinic?->ruc ?: 'No registrado' }}</p>
                <p><span class="label">Teléfono:</span> {{ $clinic?->phone ?: 'No registrado' }}</p>
                <p><span class="label">Email:</span> {{ $clinic?->email ?: 'No registrado' }}</p>
                <p><span class="label">Dirección:</span> {{ $clinic?->address ?: 'No registrada' }}</p>
            </div>
            <div class="meta">
                <strong>{{ $recordCode }}</strong>
                <p><span class="label">Fecha:</span> {{ $prescription->prescription_date?->format('d/m/Y') }}</p>
                <p><span class="label">Generado:</span> {{ $generatedAt->format('d/m/Y H:i') }}</p>
            </div>
        </header>

        <h2 class="title">Receta médica</h2>

        <section class="grid">
            <article class="box">
                <h2>Paciente</h2>
                <p class="row"><span class="label">Nombre:</span> {{ $patient?->full_name }}</p>
                <p class="row"><span class="label">Identificación:</span> {{ $patient?->identification_number ?: 'Sin registrar' }}</p>
                <p class="row"><span class="label">Edad:</span> {{ $patient?->birth_date ? $patient->birth_date->age.' años' : 'No registrada' }}</p>
                <p class="row"><span class="label">Teléfono:</span> {{ $patient?->phone ?: 'Sin registrar' }}</p>
                <p class="row"><span class="label">Alergias:</span> {{ $patient?->allergies ?: 'Sin registrar' }}</p>
            </article>

            <article class="box">
                <h2>Médico</h2>
                <p class="row"><span class="label">Nombre:</span> {{ $doctor?->user?->name ?: 'Sin registrar' }}</p>
                <p class="row"><span class="label">Especialidad:</span> {{ $doctor?->specialty?->name ?: 'Sin especialidad' }}</p>
                <p class="row"><span class="label">Licencia:</span> {{ $doctor?->license_number ?: 'Sin registrar' }}</p>
            </article>
        </section>

        @if ($prescription->consultation)
            <section class="box section">
                <h2>Consulta asociada</h2>
                <p class="row"><span class="label">Motivo:</span> {{ $prescription->consultation->reason ?: 'Sin registrar' }}</p>
                <p class="row"><span class="label">Diagnóstico:</span> {{ $prescription->consultation->diagnosis ?: 'Sin registrar' }}</p>
            </section>
        @endif

        <section class="section">
            <h2>Medicamentos</h2>
            <table>
                <thead>
                    <tr>
                        <th>Medicamento</th>
                        <th>Dosis</th>
                        <th>Frecuencia</th>
                        <th>Duración</th>
                        <th>Indicaciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($prescription->items as $item)
                        <tr>
                            <td>{{ $item->medication_name }}</td>
                            <td>{{ $item->dosage ?: 'No registrada' }}</td>
                            <td>{{ $item->frequency ?: 'No registrada' }}</td>
                            <td>{{ $item->duration ?: 'No registrada' }}</td>
                            <td>{{ $item->instructions ?: 'Sin instrucciones' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>

        <section class="box section">
            <h2>Indicaciones generales</h2>
            <p class="instructions">{{ $prescription->general_instructions ?: 'Sin indicaciones generales.' }}</p>
        </section>

        <section class="signature">
            <div class="signature-box">
                <strong>{{ $doctor?->user?->name ?: 'Médico tratante' }}</strong><br>
                Licencia profesional: {{ $doctor?->license_number ?: 'No registrada' }}
            </div>
        </section>

        <footer class="footer">
            <span>Documento generado por MediFlow</span>
            <span>{{ $generatedAt->format('d/m/Y H:i') }}</span>
        </footer>
    </main>
</body>
</html>
