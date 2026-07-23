<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePrescriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return (bool) $user
            && $user->status === 'active'
            && $user->hasAnyRole(['medico', 'administrador'])
            && $user->can('prescriptions.create');
    }

    protected function prepareForValidation(): void
    {
        $items = collect($this->input('items', []))
            ->filter(fn ($item) => is_array($item) && collect($item)->filter(fn ($value) => filled($value))->isNotEmpty())
            ->values()
            ->all();

        $this->merge(['items' => $items]);
    }

    public function rules(): array
    {
        return [
            'patient_id' => ['required', 'exists:patients,id'],
            'doctor_id' => ['required', 'exists:doctors,id'],
            'consultation_id' => ['nullable', 'exists:consultations,id'],
            'prescription_date' => ['required', 'date'],
            'general_instructions' => ['nullable', 'string'],
            'status' => ['required', 'in:active,cancelled'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.medication_name' => ['required', 'string', 'max:255'],
            'items.*.dosage' => ['nullable', 'string', 'max:255'],
            'items.*.frequency' => ['nullable', 'string', 'max:255'],
            'items.*.duration' => ['nullable', 'string', 'max:255'],
            'items.*.instructions' => ['nullable', 'string'],
        ];
    }
}
