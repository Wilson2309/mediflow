<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

class Prescription extends Model
{
    use HasFactory;

    private const IDENTITY_ATTRIBUTES = [
        'patient_id',
        'doctor_id',
        'consultation_id',
    ];

    private const SIGNATURE_ATTRIBUTES = [
        'signed_at',
        'signed_by_user_id',
        'signature_verification_code',
        'signature_hash',
        'signed_ip_address',
        'signed_user_agent',
    ];

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
            'signed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $prescription): void {
            if ($prescription->isDirty(self::IDENTITY_ATTRIBUTES)) {
                throw new LogicException('Prescription document identity is immutable.');
            }

            if ($prescription->hadSignatureArtifacts()
                && $prescription->isDirty(self::SIGNATURE_ATTRIBUTES)) {
                throw new LogicException('Prescription signature attribution is immutable.');
            }
        });

        static::deleting(function (self $prescription): void {
            if ($prescription->hasSignatureArtifacts()) {
                throw new LogicException('Signed prescriptions cannot be deleted.');
            }
        });
    }

    public function scopeWithSignatureArtifacts(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            foreach (self::SIGNATURE_ATTRIBUTES as $attribute) {
                $query->orWhereNotNull($attribute);
            }
        });
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

    public function signedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signed_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PrescriptionItem::class);
    }

    public function prescriptionItems(): HasMany
    {
        return $this->items();
    }

    public function isSigned(): bool
    {
        return filled($this->signed_at)
            && filled($this->signature_verification_code)
            && filled($this->signature_hash);
    }

    public function hasSignatureArtifacts(): bool
    {
        foreach (self::SIGNATURE_ATTRIBUTES as $attribute) {
            $value = $this->getAttribute($attribute);

            if ($value !== null && $value !== '') {
                return true;
            }
        }

        return false;
    }

    public function generateVerificationCode(): string
    {
        do {
            $code = 'RX-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (static::where('signature_verification_code', $code)->exists());

        return $code;
    }

    public function calculateSignatureHash(): string
    {
        $this->loadMissing('items');

        $payload = [
            'prescription_id' => (int) $this->id,
            'patient_id' => (int) $this->patient_id,
            'doctor_id' => (int) $this->doctor_id,
            'consultation_id' => $this->consultation_id ? (int) $this->consultation_id : null,
            'prescription_date' => $this->formatSignatureDate($this->prescription_date),
            'general_instructions' => (string) ($this->general_instructions ?? ''),
            'status' => (string) ($this->status ?? ''),
            'items' => $this->items
                ->sortBy('id')
                ->values()
                ->map(fn (PrescriptionItem $item) => [
                    'medication_name' => (string) ($item->medication_name ?? ''),
                    'dosage' => (string) ($item->dosage ?? ''),
                    'frequency' => (string) ($item->frequency ?? ''),
                    'duration' => (string) ($item->duration ?? ''),
                    'instructions' => (string) ($item->instructions ?? ''),
                ])
                ->all(),
        ];

        return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function hadSignatureArtifacts(): bool
    {
        foreach (self::SIGNATURE_ATTRIBUTES as $attribute) {
            $value = $this->getRawOriginal($attribute);

            if ($value !== null && $value !== '') {
                return true;
            }
        }

        return false;
    }

    private function formatSignatureDate(mixed $date): ?string
    {
        if ($date instanceof DateTimeInterface) {
            return $date->format('Y-m-d');
        }

        return $date ? (string) $date : null;
    }
}
