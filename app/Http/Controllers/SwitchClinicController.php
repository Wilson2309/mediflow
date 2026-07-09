<?php

namespace App\Http\Controllers;

use App\Models\Clinic;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SwitchClinicController extends Controller
{
    public function __invoke(Request $request, Clinic $clinic): RedirectResponse
    {
        $user = $request->user();
        $previousClinicId = $user->current_clinic_id;

        abort_unless(
            $user->clinics()->where('clinics.id', $clinic->id)->exists(),
            403,
            'No tienes acceso a esta clinica.'
        );

        abort_unless(
            $clinic->status === 'active',
            403,
            'Esta clinica esta inactiva.'
        );

        $user->update(['current_clinic_id' => $clinic->id]);

        AuditLogger::log('clinic.switched', 'clinics', $clinic, [], [
            'clinic_id' => $clinic->id,
            'previous_clinic_id' => $previousClinicId,
            'new_clinic_id' => $clinic->id,
            'user_id' => $user->id,
        ], 'Cambio de clinica activa.');

        return redirect()->route('dashboard')->with('success', 'Cambiaste a: '.$clinic->name);
    }
}
