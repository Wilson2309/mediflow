<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDoctorRequest extends FormRequest
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
        $doctor = $this->route('doctor');
        $doctorId = is_object($doctor) ? $doctor->id : $doctor;
        $userId = is_object($doctor) ? $doctor->user_id : null;

        return [
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'password' => 'nullable|string|min:8|confirmed',
            'specialty_id' => [
                'nullable',
                Rule::exists('specialties', 'id')->where('status', 'active'),
            ],
            'license_number' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('doctors', 'license_number')->ignore($doctorId),
            ],
            'phone' => 'nullable|string|max:30',
            'consultation_fee' => 'required|numeric|min:0|max:999999.99',
            'status' => 'required|in:active,inactive',
        ];
    }
}
