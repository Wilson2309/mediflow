<?php

namespace App\Data;

final readonly class AssistantContext
{
    public function __construct(
        public string $requestId,
        public string $question,
        public string $canonicalRole,
        public string $currentModule,
        public ?string $currentRoute,
        public string $connectionState,
        public string $locale,
        public int|string|null $knowledgeVersion,
        public string $timestamp,
    ) {
    }

    /** @return array<string, int|string|null> */
    public function toRemotePayload(): array
    {
        return [
            'request_id' => $this->requestId,
            'question' => $this->question,
            'role' => $this->canonicalRole,
            'module' => $this->currentModule,
            'route' => $this->currentRoute,
            'connection_state' => $this->connectionState,
            'locale' => $this->locale,
            'knowledge_version' => $this->knowledgeVersion,
            'timestamp' => $this->timestamp,
        ];
    }
}
