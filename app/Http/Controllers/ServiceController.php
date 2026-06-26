<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreServiceRequest;
use App\Http\Requests\UpdateServiceRequest;
use App\Models\Doctor;
use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ServiceController extends Controller
{
    public function index(Request $request): View
    {
        $clinicId = $this->clinicId();
        $search = trim((string) $request->query('search'));
        $status = $request->query('status');

        $services = Service::query()
            ->where('clinic_id', $clinicId)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when(in_array($status, ['active', 'inactive'], true), fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('services.index', [
            'services' => $services,
            'search' => $search,
            'status' => $status,
        ]);
    }

    public function create(): View
    {
        $clinicId = $this->clinicId();

        return view('services.create', [
            'doctors' => $this->doctors($clinicId),
        ]);
    }

    public function store(StoreServiceRequest $request): RedirectResponse
    {
        $clinicId = $this->clinicId();
        $validated = $request->validated();
        $doctorIds = $this->doctorIdsForClinic($validated['doctor_ids'] ?? [], $clinicId);
        unset($validated['doctor_ids']);

        $service = Service::create([
            ...$validated,
            'clinic_id' => $clinicId,
        ]);
        $service->doctors()->sync($doctorIds);

        return redirect()
            ->route('services.index')
            ->with('success', 'Servicio médico creado correctamente.');
    }

    public function show(Service $service): View
    {
        $this->authorizeClinic($service);
        $clinicId = $this->clinicId();

        return view('services.show', [
            'service' => $service->load(['doctors.user', 'doctors.specialty'])->loadCount([
                'appointments' => fn ($query) => $query->where('clinic_id', $clinicId),
                'payments' => fn ($query) => $query->where('clinic_id', $clinicId),
            ]),
        ]);
    }

    public function edit(Service $service): View
    {
        $this->authorizeClinic($service);
        $clinicId = $this->clinicId();

        return view('services.edit', [
            'service' => $service->load('doctors'),
            'doctors' => $this->doctors($clinicId),
        ]);
    }

    public function update(UpdateServiceRequest $request, Service $service): RedirectResponse
    {
        $this->authorizeClinic($service);
        $clinicId = $this->clinicId();
        $validated = $request->validated();
        $doctorIds = $this->doctorIdsForClinic($validated['doctor_ids'] ?? [], $clinicId);
        unset($validated['doctor_ids']);

        $service->update($validated);
        $service->doctors()->sync($doctorIds);

        return redirect()
            ->route('services.show', $service)
            ->with('success', 'Servicio médico actualizado correctamente.');
    }

    public function destroy(Service $service): RedirectResponse
    {
        $this->authorizeClinic($service);
        $service->delete();

        return redirect()
            ->route('services.index')
            ->with('success', 'Servicio médico eliminado correctamente.');
    }

    private function clinicId(): int
    {
        $clinicId = auth()->user()?->clinic_id;

        abort_if(! $clinicId, 403, 'El usuario autenticado no tiene una clínica asignada.');

        return (int) $clinicId;
    }

    private function authorizeClinic(Service $service): void
    {
        abort_if((int) $service->clinic_id !== $this->clinicId(), 403);
    }

    private function doctors(int $clinicId)
    {
        return Doctor::with(['user', 'specialty'])
            ->where('clinic_id', $clinicId)
            ->where('status', 'active')
            ->get()
            ->sortBy(fn (Doctor $doctor) => $doctor->user?->name ?? '');
    }

    private function doctorIdsForClinic(array $doctorIds, int $clinicId): array
    {
        $doctorIds = array_values(array_unique(array_map('intval', $doctorIds)));

        if ($doctorIds === []) {
            return [];
        }

        $validIds = Doctor::where('clinic_id', $clinicId)->whereIn('id', $doctorIds)->pluck('id')->all();

        if (count($validIds) !== count($doctorIds)) {
            throw ValidationException::withMessages([
                'doctor_ids' => 'Los médicos seleccionados no pertenecen a la clínica del usuario autenticado.',
            ]);
        }

        return $validIds;
    }
}
