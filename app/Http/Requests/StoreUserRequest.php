<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && (bool) auth()->user()?->activeClinicId();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', Rule::in(['administrador', 'medico', 'recepcionista', 'caja_finanzas'])],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'specialty_id' => ['nullable', Rule::exists('specialties', 'id')],
            'license_number' => ['nullable', 'string', 'max:255', Rule::unique('doctors', 'license_number')],
            'doctor_phone' => ['nullable', 'string', 'max:30'],
            'consultation_fee' => ['required_if:role,medico', 'nullable', 'numeric', 'min:0', 'max:999999.99'],
            'doctor_status' => ['required_if:role,medico', 'nullable', Rule::in(['active', 'inactive'])],
            'clinic_ids' => ['nullable', 'array'],
            'clinic_ids.*' => ['integer', Rule::exists('clinics', 'id')],
        ];
    }
}
