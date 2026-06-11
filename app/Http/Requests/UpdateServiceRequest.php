<?php

namespace App\Http\Requests;

use App\Models\Service;

class UpdateServiceRequest extends StoreServiceRequest
{
    public function authorize(): bool
    {
        $service = $this->route('service');
        $clinicId = auth()->user()?->clinic_id;

        return $service instanceof Service
            && $clinicId
            && (int) $service->clinic_id === (int) $clinicId;
    }
}
