<?php

namespace App\Http\Requests;

use App\Models\DemoRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDemoRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'clinic_type' => ['nullable', 'string', Rule::in(array_keys(DemoRequest::CLINIC_TYPES))],
            'doctors_count' => ['nullable', 'string', Rule::in(array_keys(DemoRequest::DOCTORS_COUNTS))],
            'interest_module' => ['nullable', 'string', Rule::in(array_keys(DemoRequest::INTEREST_MODULES))],
            'message' => ['nullable', 'string', 'max:3000'],
            'website' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function getRedirectUrl(): string
    {
        return url('/').'#contacto';
    }
}
