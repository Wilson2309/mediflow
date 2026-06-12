<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DemoRequest extends Model
{
    use HasFactory;

    public const STATUSES = [
        'pending' => 'Pendiente',
        'contacted' => 'Contactado',
        'converted' => 'Convertido',
        'discarded' => 'Descartado',
    ];

    public const CLINIC_TYPES = [
        'independent' => 'Profesional independiente',
        'private_office' => 'Consultorio privado',
        'medical_center' => 'Centro médico pequeño',
        'other' => 'Otro',
    ];

    public const DOCTORS_COUNTS = [
        '1' => '1 médico',
        '2-5' => '2 a 5 médicos',
        '6-10' => '6 a 10 médicos',
        '11+' => 'Más de 10 médicos',
    ];

    public const INTEREST_MODULES = [
        'complete_platform' => 'Plataforma completa',
        'appointments' => 'Citas médicas',
        'clinical' => 'Gestión clínica',
        'payments' => 'Pagos y finanzas',
        'reports' => 'Reportes y analítica',
        'users_roles' => 'Usuarios y roles',
    ];

    protected $fillable = [
        'full_name',
        'email',
        'phone',
        'clinic_type',
        'doctors_count',
        'interest_module',
        'message',
        'status',
        'source',
        'ip_address',
        'user_agent',
        'contacted_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'contacted_at' => 'datetime',
        ];
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeContacted(Builder $query): Builder
    {
        return $query->where('status', 'contacted');
    }

    public function scopeConverted(Builder $query): Builder
    {
        return $query->where('status', 'converted');
    }

    public function scopeDiscarded(Builder $query): Builder
    {
        return $query->where('status', 'discarded');
    }
}
