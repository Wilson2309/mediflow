<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $target = $this->route('user');
        $clinicId = auth()->user()?->clinic_id;

        return $target instanceof User
            && $clinicId
            && (int) $target->clinic_id === (int) $clinicId;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $target = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($target?->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'role' => ['required', Rule::in(['administrador', 'medico', 'recepcionista', 'caja_finanzas'])],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }
}
