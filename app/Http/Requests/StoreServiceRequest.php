<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0|max:99999999.99',
            'duration_minutes' => 'required|integer|min:1|max:1440',
            'status' => 'required|in:active,inactive',
            'doctor_ids' => 'nullable|array',
            'doctor_ids.*' => 'integer|exists:doctors,id',
        ];
    }
}

