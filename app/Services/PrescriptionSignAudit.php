<?php

namespace App\Services;

use App\Models\Prescription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class PrescriptionSignAudit
{
    private const REQUEST_ATTRIBUTE = 'prescription_sign_request_id';

    private const DENIAL_REASONS = [
        'not_owner',
        'wrong_clinic',
        'inactive_user',
        'inactive_membership',
        'inactive_clinic',
        'inactive_doctor',
        'already_signed',
        'missing_permission',
        'cancelled',
        'invalid_status',
    ];

    public function requestId(Request $request): string
    {
        $requestId = $request->attributes->get(self::REQUEST_ATTRIBUTE);

        if (is_string($requestId) && $requestId !== '') {
            return $requestId;
        }

        $requestId = (string) Str::uuid();
        $request->attributes->set(self::REQUEST_ATTRIBUTE, $requestId);

        return $requestId;
    }

    public function normalizeReason(?string $reason): string
    {
        return in_array($reason, self::DENIAL_REASONS, true) ? $reason : 'not_owner';
    }

    public function record(
        Request $request,
        Prescription $prescription,
        string $result,
        ?string $reason = null,
    ): bool {
        $actor = $request->user();
        $normalizedReason = $reason !== null ? $this->normalizeReason($reason) : null;
        $actorClinicId = (int) ($actor?->current_clinic_id ?? 0);
        $targetClinicId = 0;

        if ($normalizedReason === 'wrong_clinic') {
            $prescription->loadMissing('patient');
            $targetClinicId = (int) ($prescription->patient?->clinic_id ?? 0);
        }

        $clinicId = $targetClinicId ?: $actorClinicId;
        $values = [
            'request_id' => $this->requestId($request),
            'actor_user_id' => $actor?->id,
            'clinic_id' => $clinicId ?: null,
            'prescription_id' => (int) $prescription->getKey(),
            'result' => $result,
        ];

        if ($normalizedReason !== null) {
            $values['reason_code'] = $normalizedReason;
        }

        if ($result === 'success') {
            $values['signed_at'] = $prescription->signed_at?->toIso8601String();
        }

        $recorded = AuditLogger::log(
            action: 'prescriptions.sign',
            module: 'prescriptions',
            auditable: $prescription,
            old: [],
            new: $values,
            description: $result === 'success'
                ? 'Firma de receta registrada.'
                : 'Intento de firma de receta denegado.',
        ) !== null;

        if (! $recorded && $result === 'denied') {
            Log::warning('Prescription sign denial audit fallback.', $values);
        }

        return $recorded;
    }
}
