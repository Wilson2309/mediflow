<?php

namespace App\Contracts;

use App\Data\AssistantContext;
use App\Data\AssistantResult;

interface AssistantProvider
{
    public function answer(AssistantContext $context): AssistantResult;
}
