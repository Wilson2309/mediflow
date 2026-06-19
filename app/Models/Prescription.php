<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Prescription extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'doctor_id',
        'consultation_id',
        'prescription_date',
        'general_instructions',
        'status',
        'last_printed_at',
        'print_count',
        'last_emailed_at',
        'last_emailed_to',
        'email_count',
    ];

    protected function casts(): array
    {
        return [
            'prescription_date' => 'date',
            'last_printed_at' => 'datetime',
            'last_emailed_at' => 'datetime',
            'print_count' => 'integer',
            'email_count' => 'integer',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PrescriptionItem::class);
    }

    public function prescriptionItems(): HasMany
    {
        return $this->items();
    }
}
