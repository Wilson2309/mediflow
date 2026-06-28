<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateClinicSettingsRequest;
use App\Models\Clinic;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ClinicSettingsController extends Controller
{
    public function edit(): View
    {
        $clinic = $this->clinic()->loadCount([
            'users',
            'doctors',
            'patients',
            'services as active_services_count' => fn ($query) => $query->where('status', 'active'),
        ]);

        return view('settings.clinic', [
            'clinic' => $clinic,
        ]);
    }

    public function update(UpdateClinicSettingsRequest $request): RedirectResponse
    {
        $clinic = $this->clinic();
        $old = AuditLogger::modelSnapshot($clinic);
        $clinic->update($request->validated());
        AuditLogger::log('clinic.updated', 'settings', $clinic, $old, AuditLogger::modelSnapshot($clinic), 'Configuracion del consultorio actualizada.');

        return redirect()
            ->route('settings.clinic.edit')
            ->with('success', 'Configuración del consultorio actualizada correctamente.');
    }

    private function clinic(): Clinic
    {
        $clinicId = auth()->user()?->clinic_id;
        abort_if(! $clinicId, 403, 'El usuario autenticado no tiene una clínica asignada.');

        return Clinic::findOrFail($clinicId);
    }
}




