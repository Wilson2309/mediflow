<?php

namespace App\Services\Assistant;

use App\Data\AssistantContext;
use App\Data\AssistantResult;
use Illuminate\Support\Facades\Validator;

final class AssistantRemoteResponseValidator
{
    private const ALLOWED_FIELDS = ['answer', 'confidence', 'steps', 'suggestions', 'can_escalate'];

    /** @param array<string, mixed> $payload */
    public function validate(array $payload, AssistantContext $context): ?AssistantResult
    {
        if (array_diff(array_keys($payload), self::ALLOWED_FIELDS) !== []) {
            return null;
        }

        $validator = Validator::make($payload, [
            'answer' => ['required', 'string', 'max:'.(int) config('assistant.max_answer_length', 2000)],
            'confidence' => ['nullable', 'numeric', 'between:0,1'],
            'steps' => ['sometimes', 'array', 'max:10'],
            'steps.*' => ['string', 'max:300'],
            'suggestions' => ['sometimes', 'array', 'max:5'],
            'suggestions.*' => ['string', 'max:150'],
            'can_escalate' => ['sometimes', 'boolean'],
        ]);

        if ($validator->fails()) {
            return null;
        }

        $answer = $this->cleanText((string) $payload['answer']);
        $steps = array_map(fn (mixed $step): string => $this->cleanText((string) $step), $payload['steps'] ?? []);
        $suggestions = array_map(fn (mixed $suggestion): string => $this->cleanText((string) $suggestion), $payload['suggestions'] ?? []);
        $allText = [$answer, ...$steps, ...$suggestions];

        if ($answer === '' || collect($allText)->contains(fn (string $text): bool => $this->isUnsafe($text))) {
            return null;
        }

        return new AssistantResult(
            success: true,
            answer: $answer,
            source: 'remote',
            confidence: isset($payload['confidence']) ? (float) $payload['confidence'] : null,
            steps: $steps,
            suggestions: $suggestions,
            canEscalate: (bool) ($payload['can_escalate'] ?? false),
            fallbackUsed: false,
            requestId: $context->requestId,
        );
    }

    private function cleanText(string $value): string
    {
        $value = preg_replace('/[\x00-\x1F\x7F]/u', ' ', trim($value)) ?? '';

        return preg_replace('/\s+/u', ' ', $value) ?? '';
    }

    private function isUnsafe(string $text): bool
    {
        if ($text !== strip_tags($text)) {
            return true;
        }

        $decoded = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return preg_match('/(?:https?:\/\/|javascript:|<\s*\/?(?:script|iframe|form|button)|\bon(?:click|error)\s*=|(?:^|\s)\/[A-Za-z0-9]|\b(?:GET|POST|PUT|PATCH|DELETE)\s+\/|\b(?:php\s+artisan|npm(?:\.cmd)?\s+|curl\s+|powershell\s+|cmd(?:\.exe)?\s+|rm\s+|del\s+))/iu', $decoded) === 1;
    }
}
