<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['clinic_id', 'current_clinic_id', 'name', 'email', 'phone', 'password', 'status'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function doctor(): HasOne
    {
        return $this->hasOne(Doctor::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    /** Many-to-many: all clinics this user has access to */
    public function clinics(): BelongsToMany
    {
        return $this->belongsToMany(Clinic::class, 'clinic_user')->withTimestamps();
    }

    /** The clinic the user is currently viewing */
    public function currentClinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class, 'current_clinic_id');
    }

    public function resolvedClinic(): ?Clinic
    {
        if (! $this->exists) {
            return null;
        }

        if ($this->current_clinic_id) {
            $clinic = $this->clinics()->whereKey($this->current_clinic_id)->first();

            if ($clinic) {
                return $clinic;
            }

            $this->forceFill(['current_clinic_id' => null])->saveQuietly();
            $this->current_clinic_id = null;
        }

        $clinic = $this->clinics()
            ->where('status', 'active')
            ->orderBy('clinics.id')
            ->first();

        if ($clinic) {
            $updates = ['current_clinic_id' => $clinic->id];

            if (! $this->clinic_id) {
                $updates['clinic_id'] = $clinic->id;
            }

            $this->forceFill($updates)->saveQuietly();
            $this->forceFill($updates);
        }

        return $clinic;
    }

    public function activeClinic(): ?Clinic
    {
        $clinic = $this->resolvedClinic();

        return $clinic?->status === 'active' ? $clinic : null;
    }

    /** Resolve the active clinic_id from the validated clinic_user assignment. */
    public function activeClinicId(): ?int
    {
        return $this->activeClinic()?->id;
    }
}
