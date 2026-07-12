<?php

namespace App\Services\Assistant;

use App\Contracts\AssistantProvider;
use App\Data\AssistantContext;
use App\Data\AssistantResult;

final class NullAssistantProvider implements AssistantProvider
{
    public function answer(AssistantContext $context): AssistantResult
    {
        return AssistantResult::fallback($context->requestId, 'PROVIDER_UNAVAILABLE');
    }
}
