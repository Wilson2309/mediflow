<?php

namespace App\Mail;

use App\Models\Prescription;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PrescriptionMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Prescription $prescription,
        private readonly string $pdfContent,
        private readonly string $fileName,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Receta medica - MediFlow',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.prescription',
            with: [
                'prescription' => $this->prescription,
                'patient' => $this->prescription->patient,
                'doctor' => $this->prescription->doctor,
                'clinic' => $this->prescription->patient?->clinic,
            ],
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->pdfContent, $this->fileName)
                ->withMime('application/pdf'),
        ];
    }
}
