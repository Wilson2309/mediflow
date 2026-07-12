<?php

namespace App\Services\Assistant;

final class AssistantInputSanitizer
{
    public function question(string $value): string
    {
        return $this->plainText($value, (int) config('assistant.max_question_length', 500));
    }

    public function route(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $sanitized = preg_replace('/[^a-zA-Z0-9._\/-]/', '', $value) ?? '';

        return mb_substr($sanitized, 0, 255) ?: null;
    }

    private function plainText(string $value, int $maxLength): string
    {
        $value = strip_tags($value);
        $value = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $value) ?? '';
        $value = preg_replace('/\s+/u', ' ', $value) ?? '';

        return mb_substr(trim($value), 0, $maxLength);
    }
}
