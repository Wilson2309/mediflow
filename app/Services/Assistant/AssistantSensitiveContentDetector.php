<?php

namespace App\Services\Assistant;

final class AssistantSensitiveContentDetector
{
    /** @var array<int, string> */
    private const PATTERNS = [
        '/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/iu',
        '/\b(?:\d[ -]*?){13,19}\b/u',
        '/\b\d{7,12}\b/u',
        '/(?:\+?\d{1,3}[\s.-]?)?(?:\(?\d{2,3}\)?[\s.-]?)\d{3}[\s.-]?\d{4}\b/u',
        '/\b(?:password|contraseña|token|api[ _-]?key|clave secreta)\s*[:=]\s*\S+/iu',
        '/\b(?:diagnóstico de|historia clínica de|receta de)\s+[^?.,;]{2,}/iu',
        '/\b(?:paciente|cédula|identificación)\s*[:=]\s*[^?.,;]{2,}/iu',
        '/\bpaciente\s+[A-ZÁÉÍÓÚÑ][a-záéíóúñ]+\s+[A-ZÁÉÍÓÚÑ][a-záéíóúñ]+/u',
        '/<\s*(?:script|iframe|form|input|button)\b/iu',
    ];

    public function containsSensitiveData(string $question): bool
    {
        foreach (self::PATTERNS as $pattern) {
            if (preg_match($pattern, $question) === 1) {
                return true;
            }
        }

        return false;
    }
}
