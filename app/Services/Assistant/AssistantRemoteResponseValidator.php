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
        if (count($payload) !== count(self::ALLOWED_FIELDS)
            || array_diff(array_keys($payload), self::ALLOWED_FIELDS) !== []) {
            return null;
        }

        if (! is_string($payload['answer'] ?? null)
            || (! is_int($payload['confidence'] ?? null) && ! is_float($payload['confidence'] ?? null))
            || ! is_array($payload['steps'] ?? null)
            || collect($payload['steps'])->contains(fn (mixed $step): bool => ! is_string($step))
            || ! is_array($payload['suggestions'] ?? null)
            || collect($payload['suggestions'])->contains(fn (mixed $suggestion): bool => ! is_string($suggestion))
            || ! is_bool($payload['can_escalate'] ?? null)) {
            return null;
        }

        $validator = Validator::make($payload, [
            'answer' => ['required', 'string', 'max:'.(int) config('assistant.max_answer_length', 2000)],
            'confidence' => ['required', 'numeric', 'between:0,1'],
            'steps' => ['present', 'array', 'max:10'],
            'steps.*' => ['required', 'string', 'min:1', 'max:300'],
            'suggestions' => ['present', 'array', 'max:5'],
            'suggestions.*' => ['required', 'string', 'min:1', 'max:150'],
            'can_escalate' => ['required', 'boolean'],
        ]);

        if ($validator->fails()) {
            return null;
        }

        $answer = $this->cleanText((string) $payload['answer']);
        $steps = array_map(fn (mixed $step): string => $this->cleanText((string) $step), $payload['steps'] ?? []);
        $suggestions = array_map(fn (mixed $suggestion): string => $this->cleanText((string) $suggestion), $payload['suggestions'] ?? []);
        $allText = [$answer, ...$steps, ...$suggestions];

        if (collect($allText)->contains(
            fn (string $text): bool => $text === '' || $this->isUnsafe($text),
        )) {
            return null;
        }

        return new AssistantResult(
            success: true,
            answer: $answer,
            source: 'remote',
            confidence: (float) $payload['confidence'],
            steps: $steps,
            suggestions: $suggestions,
            canEscalate: $payload['can_escalate'],
            fallbackUsed: false,
            requestId: $context->requestId,
        );
    }

    private function cleanText(string $value): string
    {
        $value = preg_replace('/[\x00-\x1F\x7F]/u', ' ', trim($value)) ?? '';

        return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    }

    private function isUnsafe(string $text): bool
    {
        if ($text !== strip_tags($text)) {
            return true;
        }

        $decoded = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        if (preg_match('/(?:https?:\/\/|javascript:|<\s*\/?(?:script|iframe|form|button)|\bon(?:click|error)\s*=|(?:^|\s)\/[A-Za-z0-9]|\b(?:GET|POST|PUT|PATCH|DELETE)\s+\/|\b(?:php\s+artisan|npm(?:\.cmd)?\s+|curl\s+|powershell\s+|cmd(?:\.exe)?\s+|rm\s+))/iu', $decoded) === 1) {
            return true;
        }

        return preg_match('/(?:^|[\r\n;&|])\s*del(?:\.exe)?\s+(?:\/[a-z]\s+)*(?:"[^"\r\n]+"|\'[^\'\r\n]+\'|(?:[a-z]:)?[\\\\\/]|\.\.?[\\\\\/]|[*?]|[a-z0-9_-]+\.[a-z0-9]{1,8}(?:\s|$))/iu', $decoded) === 1;
    }
}
