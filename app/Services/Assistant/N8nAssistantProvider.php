<?php

namespace App\Services\Assistant;

use App\Contracts\AssistantProvider;
use App\Data\AssistantContext;
use App\Data\AssistantResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use JsonException;
use Throwable;

final class N8nAssistantProvider implements AssistantProvider
{
    public function __construct(
        private readonly AssistantRemoteResponseValidator $responseValidator,
    ) {
    }

    public function answer(AssistantContext $context): AssistantResult
    {
        $url = (string) config('assistant.n8n.webhook_url', '');
        $secret = (string) config('assistant.n8n.secret', '');

        if ($url === '' || $secret === '') {
            return AssistantResult::fallback($context->requestId, 'PROVIDER_UNAVAILABLE');
        }

        $startedAt = microtime(true);
        $body = json_encode(
            $context->toRemotePayload(),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );
        $signature = hash_hmac('sha256', $context->timestamp.'.'.$body, $secret);

        try {
            $response = Http::timeout((int) config('assistant.timeout_seconds', 8))
                ->acceptJson()
                ->withHeaders([
                    'X-MediFlow-Request-Id' => $context->requestId,
                    'X-MediFlow-Timestamp' => $context->timestamp,
                    'X-MediFlow-Signature' => $signature,
                    'X-MediFlow-Assistant-Version' => (string) ($context->knowledgeVersion ?? 'unknown'),
                ])
                ->withBody($body, 'application/json')
                ->post($url);

            if (! $response->successful()) {
                $this->logFailure($context, 'PROVIDER_UNAVAILABLE', $startedAt, $response->status());

                return AssistantResult::fallback($context->requestId, 'PROVIDER_UNAVAILABLE');
            }

            try {
                $payload = json_decode($response->body(), true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                $this->logFailure($context, 'INVALID_REMOTE_RESPONSE', $startedAt, $response->status());

                return AssistantResult::fallback($context->requestId, 'INVALID_REMOTE_RESPONSE');
            }

            if (! is_array($payload)) {
                $this->logFailure($context, 'INVALID_REMOTE_RESPONSE', $startedAt, $response->status());

                return AssistantResult::fallback($context->requestId, 'INVALID_REMOTE_RESPONSE');
            }

            $result = $this->responseValidator->validate($payload, $context);
            if (! $result) {
                $this->logFailure($context, 'INVALID_REMOTE_RESPONSE', $startedAt, $response->status());

                return AssistantResult::fallback($context->requestId, 'INVALID_REMOTE_RESPONSE');
            }

            return $result;
        } catch (Throwable) {
            $this->logFailure($context, 'PROVIDER_UNAVAILABLE', $startedAt);

            return AssistantResult::fallback($context->requestId, 'PROVIDER_UNAVAILABLE');
        }
    }

    private function logFailure(
        AssistantContext $context,
        string $code,
        float $startedAt,
        ?int $httpStatus = null,
    ): void {
        Log::warning('assistant.remote_provider_failed', array_filter([
            'request_id' => $context->requestId,
            'provider' => 'n8n',
            'result_status' => 'fallback',
            'response_time_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'role' => $context->canonicalRole,
            'module' => $context->currentModule,
            'error_code' => $code,
            'http_status' => $httpStatus,
        ], static fn (mixed $value): bool => $value !== null));
    }
}
