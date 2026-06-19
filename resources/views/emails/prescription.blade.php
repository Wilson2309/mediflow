<div style="font-family: Arial, sans-serif; color: #0F172A; line-height: 1.6;">
    <h1 style="font-size: 20px; margin-bottom: 16px;">Receta médica</h1>

    <p>Hola {{ $patient?->full_name ?? 'paciente' }},</p>

    <p>
        {{ $clinic?->name ?? 'El consultorio' }} le envía su receta médica emitida por
        {{ $doctor?->user?->name ?? 'su médico' }} el
        {{ $prescription->prescription_date?->format('d/m/Y') }}.
    </p>

    <p>Encontrara el documento PDF adjunto a este correo.</p>

    <p style="margin-top: 24px;">
        Saludos,<br>
        <strong>{{ $clinic?->name ?? 'MediFlow' }}</strong>
    </p>
</div>
