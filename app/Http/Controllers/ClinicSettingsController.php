<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateClinicSettingsRequest;
use App\Models\Clinic;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
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
        
        $data = $request->validated();
        
        if ($request->hasFile('logo')) {
            if ($clinic->logo_path) {
                Storage::disk('public')->delete($clinic->logo_path);
            }
            $data['logo_path'] = $request->file('logo')->store('logos', 'public');
        }

        $clinic->update($data);
        AuditLogger::log('clinic.updated', 'settings', $clinic, $old, AuditLogger::modelSnapshot($clinic), 'Configuracion del consultorio actualizada.');

        return redirect()
            ->route('settings.clinic.edit')
            ->with('success', 'Configuración del consultorio actualizada correctamente.');
    }

    private function clinic(): Clinic
    {
        $clinicId = auth()->user()?->activeClinicId();
        abort_if(! $clinicId, 403, 'El usuario autenticado no tiene una clínica asignada.');

        return Clinic::findOrFail($clinicId);
    }
}




