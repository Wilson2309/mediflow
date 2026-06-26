<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePatientRequest extends FormRequest
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
        $patient = $this->route('patient');
        $patientId = is_object($patient) ? $patient->id : $patient;

        return [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'identification_number' => [
                'nullable',
                'string',
                'max:30',
                Rule::unique('patients', 'identification_number')->ignore($patientId),
            ],
            'birth_date' => 'nullable|date',
            'gender' => 'nullable|string|max:30',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:255',
            'blood_type' => ['nullable', Rule::in(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'])],
            'allergies' => 'nullable|string',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:30',
            'status' => 'required|in:active,inactive',
        ];
    }
}

