<?php

namespace App\Http\Controllers;

use App\Models\Clinic;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SwitchClinicController extends Controller
{
    public function __invoke(Request $request, Clinic $clinic): RedirectResponse
    {
        $user = $request->user();

        // Security: only allow switching to clinics the user has access to
        abort_unless($user->clinics()->where('clinics.id', $clinic->id)->exists(), 403, 'No tienes acceso a esta clínica.');

        // Security: only active clinics
        abort_unless($clinic->status === 'active', 403, 'Esta clínica está inactiva.');

        $user->update(['current_clinic_id' => $clinic->id]);

        return redirect()->route('dashboard')->with('success', 'Cambiaste a: ' . $clinic->name);
    }
}
