<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $clinicId = auth()->user()?->activeClinicId();
        $routeName = (string) $this->route()?->getName();
        $rules = [
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'doctor_id' => ['nullable', 'integer', Rule::exists('doctors', 'id')->where('clinic_id', $clinicId)],
            'service_id' => ['nullable', 'integer', Rule::exists('services', 'id')->where('clinic_id', $clinicId)],
            'patient_id' => ['nullable', 'integer', Rule::exists('patients', 'id')->where('clinic_id', $clinicId)],
            'specialty_id' => ['nullable', 'integer', 'exists:specialties,id'],
            'payment_status' => ['nullable', Rule::in(['pending', 'paid', 'cancelled', 'refunded'])],
            'payment_method' => ['nullable', Rule::in(['cash', 'card', 'transfer', 'other'])],
        ];

        $rules['status'] = match ($routeName) {
            'reports.appointments' => ['nullable', Rule::in(['scheduled', 'confirmed', 'completed', 'cancelled', 'no_show'])],
            'reports.patients', 'reports.doctors', 'reports.services' => ['nullable', Rule::in(['active', 'inactive'])],
            default => ['nullable'],
        };

        return $rules;
    }
}
