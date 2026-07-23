<?php

namespace App\Http\Requests;

use App\Models\Prescription;
use App\Services\PrescriptionAuthorizationAudit;
use App\Support\ControlledResponse;
use Illuminate\Auth\Access\Response as AccessResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Gate;

class UpdatePrescriptionRequest extends StorePrescriptionRequest
{
    private ?AccessResponse $authorizationDecision = null;

    public function authorize(): bool
    {
        $prescription = $this->route('prescription');

        if (! $prescription instanceof Prescription) {
            return false;
        }

        $this->authorizationDecision = Gate::inspect('update', $prescription);

        return $this->authorizationDecision->allowed();
    }

    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['patient_id'], $rules['doctor_id'], $rules['consultation_id']);

        return $rules;
    }

    protected function failedAuthorization(): void
    {
        $prescription = $this->route('prescription');
        $reason = (string) ($this->authorizationDecision?->message() ?? 'missing_permission');

        if ($prescription instanceof Prescription) {
            app(PrescriptionAuthorizationAudit::class)->record(
                prescription: $prescription,
                operation: 'update',
                reason: $reason,
            );
        }

        if ($reason === 'wrong_clinic') {
            throw new HttpResponseException(
                ControlledResponse::error($this, 404, 'RESOURCE_NOT_FOUND'),
            );
        }

        if (in_array($reason, ['already_signed', 'cancelled', 'invalid_status'], true)) {
            $response = $this->expectsJson()
                ? ControlledResponse::jsonError(409, 'RESOURCE_STATE_CONFLICT')
                : redirect()
                    ->route('prescriptions.show', $prescription)
                    ->with('error', 'No se puede editar una receta firmada. Anule o cree una nueva receta.');

            throw new HttpResponseException($response);
        }

        throw new HttpResponseException(
            ControlledResponse::error($this, 403, 'OPERATION_NOT_AUTHORIZED'),
        );
    }

    protected function failedValidation(Validator $validator): void
    {
        if ($this->expectsJson()) {
            throw new HttpResponseException(
                ControlledResponse::jsonError(422, 'VALIDATION_ERROR', [
                    'errors' => $validator->errors()->toArray(),
                ]),
            );
        }

        parent::failedValidation($validator);
    }
}
