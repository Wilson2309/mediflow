<?php

namespace App\Http\Requests;

use App\Services\Assistant\AssistantKnowledgeCatalog;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class AssistantMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    /** @return array<string, mixed> */
    public function rules(AssistantKnowledgeCatalog $catalog): array
    {
        return [
            'question' => ['required', 'string', 'min:3', 'max:'.(int) config('assistant.max_question_length', 500)],
            'current_route' => ['nullable', 'string', 'max:255'],
            'current_module' => ['nullable', 'string', 'max:100', Rule::in($catalog->modules())],
            'connection_state' => ['nullable', Rule::in(['ONLINE', 'INTERNET_UNAVAILABLE', 'SERVER_UNAVAILABLE', 'OFFLINE'])],
            'knowledge_version' => [
                'nullable',
                static function (string $attribute, mixed $value, Closure $fail): void {
                    if (is_int($value)) {
                        return;
                    }

                    if (! is_string($value) || preg_match('/^[A-Za-z0-9._-]{1,30}$/', $value) !== 1) {
                        $fail('La versión de conocimiento no es válida.');
                    }
                },
            ],
            'user_id' => ['prohibited'],
            'role' => ['prohibited'],
            'clinic_id' => ['prohibited'],
            'clinic_name' => ['prohibited'],
            'permissions' => ['prohibited'],
            'doctor_id' => ['prohibited'],
            'patient_id' => ['prohibited'],
            'payment_id' => ['prohibited'],
            'diagnosis' => ['prohibited'],
            'prescription' => ['prohibited'],
            'medical_record' => ['prohibited'],
            'card_number' => ['prohibited'],
            'password' => ['prohibited'],
            'token' => ['prohibited'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'ok' => false,
            'message' => 'Los datos enviados no son válidos.',
            'errors' => $validator->errors()->toArray(),
            'code' => 'VALIDATION_ERROR',
        ], 422));
    }
}
