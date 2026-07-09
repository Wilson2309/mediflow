<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Throwable;

class AuditLogger
{
    private const SENSITIVE_KEYS = [
        'password',
        'current_password',
        'password_confirmation',
        'remember_token',
        'token',
        'tokens',
        'api_key',
        'api_keys',
        'secret',
        'smtp_password',
        'mail_password',
        'credentials',
        'signature_hash',
    ];

    private const CLINICAL_TEXT_KEYS = [
        'diagnosis',
        'treatment',
        'symptoms',
        'observations',
        'reason',
        'personal_history',
        'family_history',
        'surgical_history',
        'current_medications',
        'chronic_diseases',
        'allergies',
        'general_instructions',
        'instructions',
    ];

    public static function log(
        string $action,
        string $module,
        ?Model $auditable = null,
        array $old = [],
        array $new = [],
        ?string $description = null
    ): ?AuditLog {
        try {
            if (! Schema::hasTable('audit_logs')) {
                return null;
            }

            return AuditLog::create([
                'clinic_id' => self::clinicId($auditable, $old, $new),
                'user_id' => auth()->id(),
                'action' => $action,
                'auditable_type' => $auditable ? $auditable::class : null,
                'auditable_id' => $auditable?->getKey(),
                'module' => $module,
                'description' => $description,
                'old_values' => self::sanitize($old),
                'new_values' => self::sanitize($new),
                'ip_address' => request()?->ip(),
                'user_agent' => self::limitString((string) request()?->userAgent(), 500),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    public static function modelSnapshot(Model $model, array $only = []): array
    {
        $attributes = $model->getAttributes();

        if ($only !== []) {
            $attributes = array_intersect_key($attributes, array_flip($only));
        }

        return self::sanitize($attributes);
    }

    private static function clinicId(?Model $auditable, array $old = [], array $new = []): ?int
    {
        $explicitClinicId = $new['clinic_id'] ?? $old['clinic_id'] ?? null;

        if ($explicitClinicId) {
            return (int) $explicitClinicId;
        }

        $clinicId = auth()->user()?->activeClinicId();

        if ($clinicId) {
            return (int) $clinicId;
        }

        if (! $auditable) {
            return null;
        }

        if (isset($auditable->clinic_id)) {
            return (int) $auditable->clinic_id;
        }

        if ($auditable->relationLoaded('patient') && $auditable->patient?->clinic_id) {
            return (int) $auditable->patient->clinic_id;
        }

        $legacyClinicId = auth()->user()?->clinic_id;

        if ($legacyClinicId && ! auth()->user()?->hasRole('super_admin')) {
            return (int) $legacyClinicId;
        }

        return null;
    }

    private static function sanitize(array $values): array
    {
        $clean = [];

        foreach ($values as $key => $value) {
            $normalizedKey = strtolower((string) $key);

            if (self::isSensitiveKey($normalizedKey)) {
                continue;
            }

            if (in_array($normalizedKey, self::CLINICAL_TEXT_KEYS, true)) {
                $clean[$key] = self::clinicalSummary($value);
                continue;
            }

            $clean[$key] = self::sanitizeValue($value);
        }

        return $clean;
    }

    private static function sanitizeValue(mixed $value): mixed
    {
        if (is_array($value)) {
            return self::sanitize($value);
        }

        if ($value instanceof Model) {
            return [
                'type' => $value::class,
                'id' => $value->getKey(),
            ];
        }

        if (is_string($value)) {
            return self::limitString($value);
        }

        return $value;
    }

    private static function isSensitiveKey(string $key): bool
    {
        foreach (self::SENSITIVE_KEYS as $sensitiveKey) {
            if (str_contains($key, $sensitiveKey)) {
                return true;
            }
        }

        return false;
    }

    private static function clinicalSummary(mixed $value): string|int|float|null|bool|array
    {
        if ($value === null || is_bool($value) || is_numeric($value)) {
            return $value;
        }

        if (is_array($value)) {
            return '[contenido clínico omitido]';
        }

        $text = trim((string) $value);

        if ($text === '') {
            return null;
        }

        return '[contenido clínico omitido: '.mb_strlen($text).' caracteres]';
    }

    private static function limitString(string $value, int $limit = 180): string
    {
        $value = trim($value);

        if (mb_strlen($value) <= $limit) {
            return $value;
        }

        return mb_substr($value, 0, $limit - 3).'...';
    }
}
