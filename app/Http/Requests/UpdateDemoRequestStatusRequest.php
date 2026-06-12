<?php

namespace App\Http\Requests;

use App\Models\DemoRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDemoRequestStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(array_keys(DemoRequest::STATUSES))],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
