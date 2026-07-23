<?php

namespace App\Services;

use App\Models\Prescription;

final class PrescriptionAuthorizationAudit
{
    private const ACTIONS = [
        'view' => 'prescription.view_denied',
        'update' => 'prescription.update_denied',
        'delete' => 'prescription.delete_denied',
        'send' => 'prescription.send_denied',
    ];

    public function record(
        Prescription $prescription,
        string $operation,
        string $reason,
    ): bool {
        $action = self::ACTIONS[$operation] ?? 'prescription.access_denied';
        $values = [
            'result' => 'denied',
            'reason_code' => $reason,
        ];

        if ($reason === 'wrong_clinic') {
            $prescription->loadMissing('patient');
            $targetClinicId = (int) ($prescription->patient?->clinic_id ?? 0);

            if ($targetClinicId !== 0) {
                $values['clinic_id'] = $targetClinicId;
            }
        }

        return AuditLogger::log(
            action: $action,
            module: 'prescriptions',
            auditable: $prescription,
            old: [],
            new: $values,
            description: 'Operación de receta denegada.',
        ) !== null;
    }
}
