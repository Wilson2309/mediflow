<?php

namespace App\Http\Controllers\Concerns;

trait ResolvesClinic
{
    /**
     * Resolve the active clinic ID for the authenticated user.
     * Uses current_clinic_id (from tenant switcher) with fallback to clinic_id.
     */
    private function clinicId(): int
    {
        $clinicId = auth()->user()?->activeClinicId();

        abort_if(! $clinicId, 403, 'El usuario autenticado no tiene una clínica asignada.');

        return (int) $clinicId;
    }
}
