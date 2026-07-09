<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMedicalRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $medicalRecord = $this->route('medical_record');

        return [
            'patient_id' => [
                'required',
                'exists:patients,id',
                Rule::unique('medical_records', 'patient_id')->ignore($medicalRecord?->id),
            ],
            'personal_history' => 'nullable|string',
            'family_history' => 'nullable|string',
            'surgical_history' => 'nullable|string',
            'allergies' => 'nullable|string',
            'habits' => 'nullable|string',
            'current_medications' => 'nullable|string',
            'chronic_diseases' => 'nullable|string',
            'observations' => 'nullable|string',
        ];
    }
}
