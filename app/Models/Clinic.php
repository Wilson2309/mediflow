<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Clinic extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'legal_name',
        'ruc',
        'legal_representative',
        'phone',
        'secondary_phone',
        'email',
        'website',
        'address',
        'country',
        'state',
        'city',
        'clinic_type',
        'logo_path',
        'subscription_plan',
        'subscription_end_date',
        'internal_notes',
        'status',
    ];

    protected $casts = [
        'subscription_end_date' => 'date',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function doctors(): HasMany
    {
        return $this->hasMany(Doctor::class);
    }

    public function patients(): HasMany
    {
        return $this->hasMany(Patient::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
