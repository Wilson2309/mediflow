<?php

namespace App\Support;

final class RolePermissions
{
    /** @return array<int, string> */
    public static function all(): array
    {
        return [
            'dashboard.view',
            ...self::resource('patients'),
            ...self::resource('doctors'),
            ...self::resource('services'),
            ...self::resource('appointments'),
            ...self::resource('consultations'),
            ...self::resource('medical_records'),
            ...self::resource('prescriptions'),
            'prescriptions.sign',
            ...self::resource('payments'),
            'demo_requests.view',
            'demo_requests.update',
            'demo_requests.delete',
            'reports.view',
            'reports.appointments',
            'reports.clinical',
            'reports.financial',
            'reports.patients',
            'reports.doctors',
            'reports.services',
            'audit_logs.view',
            ...self::resource('users'),
            'settings.clinic.view',
            'settings.clinic.update',
            'super_admin.access',
        ];
    }

    /** @return array<string, array<int, string>> */
    public static function byRole(): array
    {
        $administratorPermissions = array_values(array_diff(self::all(), [
            'super_admin.access',
            'prescriptions.sign',
        ]));

        return [
            'super_admin' => [
                'super_admin.access',
            ],
            'administrador' => $administratorPermissions,
            'medico' => [
                'dashboard.view',
                'patients.view',
                'appointments.view',
                'consultations.view',
                'consultations.create',
                'consultations.update',
                'medical_records.view',
                'medical_records.create',
                'medical_records.update',
                'prescriptions.view',
                'prescriptions.create',
                'prescriptions.update',
                'prescriptions.sign',
                'reports.view',
                'reports.clinical',
                'reports.appointments',
            ],
            'recepcionista' => [
                'dashboard.view',
                'patients.view',
                'patients.create',
                'patients.update',
                'appointments.view',
                'appointments.create',
                'appointments.update',
                'medical_records.view',
                'medical_records.create',
                'medical_records.update',
                'services.view',
                'doctors.view',
                'demo_requests.view',
                'demo_requests.update',
            ],
            'caja_finanzas' => [
                'dashboard.view',
                'patients.view',
                'appointments.view',
                'services.view',
                'payments.view',
                'payments.create',
                'payments.update',
                'reports.view',
                'reports.financial',
            ],
        ];
    }

    /** @return array<int, string> */
    private static function resource(string $module): array
    {
        return [
            "{$module}.view",
            "{$module}.create",
            "{$module}.update",
            "{$module}.delete",
        ];
    }
}
