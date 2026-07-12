<?php

namespace App\Data;

final readonly class AssistantResult
{
    public const FALLBACK_ANSWER = 'No encontré una respuesta exacta para eso. Puedes revisar la guía del módulo o contactar al administrador.';

    public const SENSITIVE_ANSWER = 'No puedo procesar esa pregunta porque parece contener información sensible. Reformúlala sin nombres de pacientes, identificaciones ni datos clínicos.';

    /**
     * @param array<int, string> $steps
     * @param array<int, string> $suggestions
     */
    public function __construct(
        public bool $success,
        public string $answer,
        public string $source,
        public ?float $confidence,
        public array $steps,
        public array $suggestions,
        public bool $canEscalate,
        public bool $fallbackUsed,
        public string $requestId,
        public ?string $code = null,
    ) {
    }

    public static function fallback(string $requestId, string $code): self
    {
        return new self(
            success: true,
            answer: self::FALLBACK_ANSWER,
            source: 'fallback',
            confidence: null,
            steps: [],
            suggestions: [],
            canEscalate: true,
            fallbackUsed: true,
            requestId: $requestId,
            code: $code,
        );
    }

    public static function sensitive(string $requestId): self
    {
        return new self(
            success: false,
            answer: self::SENSITIVE_ANSWER,
            source: 'local',
            confidence: null,
            steps: [],
            suggestions: [],
            canEscalate: false,
            fallbackUsed: false,
            requestId: $requestId,
            code: 'SENSITIVE_CONTENT',
        );
    }

    /** @return array<string, mixed> */
    public function toResponseArray(): array
    {
        return array_filter([
            'ok' => $this->success,
            'request_id' => $this->requestId,
            'answer' => $this->answer,
            'steps' => $this->steps,
            'suggestions' => $this->suggestions,
            'confidence' => $this->confidence,
            'source' => $this->source,
            'can_escalate' => $this->canEscalate,
            'fallback_used' => $this->fallbackUsed,
            'code' => $this->code,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
