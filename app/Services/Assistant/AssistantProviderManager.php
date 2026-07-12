<?php

namespace App\Services\Assistant;

use App\Contracts\AssistantProvider;

final class AssistantProviderManager
{
    public function __construct(
        private readonly NullAssistantProvider $nullProvider,
        private readonly N8nAssistantProvider $n8nProvider,
    ) {
    }

    public function resolve(): AssistantProvider
    {
        return match (config('assistant.provider')) {
            'n8n' => $this->n8nProvider,
            default => $this->nullProvider,
        };
    }
}
