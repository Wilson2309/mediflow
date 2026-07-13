<?php

namespace App\Services\Assistant;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

final class N8nKnowledgeSyncService
{
    public function __construct(
        private readonly AssistantHmacSigner $signer,
        private readonly AssistantN8nDocumentPackage $documentPackage,
    ) {
    }

    /**
     * @param  array<string, mixed>  $package
     * @param  null|callable(int, int, int): void  $onBatchAccepted
     * @return array<string, mixed>
     */
    public function sync(
        array $package,
        string $provider,
        int $batchSize,
        ?callable $onBatchAccepted = null,
    ): array {
        if (! $this->validPackageEnvelope($package)
            || ! in_array($provider, ['supabase', 'simple'], true)
            || $batchSize < 1
            || $batchSize > 100) {
            return $this->failure($package, 'INVALID_INGEST_PACKAGE');
        }

        try {
            $this->documentPackage->assertValid($package);
        } catch (Throwable) {
            return $this->failure($package, 'INVALID_INGEST_PACKAGE');
        }

        $url = (string) config('assistant.n8n.ingest_webhook_url', '');
        $secret = (string) config('assistant.n8n.ingest_secret', '');

        if ($url === '' || $secret === '') {
            return $this->failure($package, 'INGEST_NOT_CONFIGURED');
        }

        $documents = $package['documents'];
        $batches = array_chunk($documents, $batchSize);
        $batchCount = count($batches);
        $acceptedTotal = 0;
        $sentTotal = 0;
        $attemptedBatches = 0;
        $maxAttempts = $provider === 'supabase' ? 2 : 1;

        foreach ($batches as $batchIndex => $documentsBatch) {
            $responseData = null;
            $lastCode = 'INGEST_UNAVAILABLE';
            $partialAccepted = 0;
            $partialRejected = 0;
            $sentTotal += count($documentsBatch);
            $attemptedBatches++;

            for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                $requestId = Str::uuid()->toString();
                $timestamp = now('UTC')->toIso8601String();
                $payload = [
                    'request_id' => $requestId,
                    'provider' => $provider,
                    'batch_index' => $batchIndex,
                    'batch_count' => $batchCount,
                    'full_manifest' => true,
                    'checksum' => $package['checksum'],
                    'knowledge_version' => $package['knowledge_version'],
                    'document_count' => $package['document_count'],
                    'documents' => $documentsBatch,
                    'timestamp' => $timestamp,
                ];
                $rawBody = $this->signer->encode($payload);

                try {
                    $response = Http::timeout((int) config('assistant.n8n.ingest_timeout_seconds', 30))
                        ->acceptJson()
                        ->withHeaders($this->signer->headers(
                            $requestId,
                            $timestamp,
                            $rawBody,
                            $secret,
                            $package['knowledge_version'],
                        ))
                        ->withBody($rawBody, 'application/json')
                        ->post($url);
                } catch (ConnectionException) {
                    $lastCode = 'INGEST_CONNECTION_FAILED';
                    continue;
                } catch (Throwable) {
                    return $this->failure(
                        $package,
                        'INGEST_UNAVAILABLE',
                        $acceptedTotal,
                        0,
                        $sentTotal,
                        $attemptedBatches,
                    );
                }

                if (! $response->successful()) {
                    $lastCode = 'INGEST_HTTP_ERROR';
                    if ($attempt < $maxAttempts && $this->isSafelyRetryable($response)) {
                        continue;
                    }

                    if ($response->clientError() && $response->status() !== 408) {
                        $partialRejected = count($documentsBatch);
                    }

                    break;
                }

                $responseData = $response->json();
                if (! $this->validResponse(
                    $responseData,
                    $requestId,
                    count($documentsBatch),
                    $package,
                    $batchIndex === $batchCount - 1,
                )) {
                    $lastCode = 'INVALID_INGEST_RESPONSE';
                    [$partialAccepted, $partialRejected] = $this->safePartialCounts(
                        $responseData,
                        count($documentsBatch),
                        $requestId,
                        $package,
                    );
                    $responseData = null;
                }

                break;
            }

            if (! is_array($responseData)) {
                return $this->failure(
                    $package,
                    $lastCode,
                    $acceptedTotal + $partialAccepted,
                    $partialRejected,
                    $sentTotal,
                    $attemptedBatches,
                );
            }

            $acceptedTotal += (int) $responseData['accepted'];
            if ($onBatchAccepted) {
                $onBatchAccepted($batchIndex + 1, $batchCount, (int) $responseData['accepted']);
            }
        }

        return [
            'success' => true,
            'documents_sent' => $sentTotal,
            'accepted' => $acceptedTotal,
            'rejected' => 0,
            'batches' => $attemptedBatches,
            'checksum' => $package['checksum'],
            'knowledge_version' => $package['knowledge_version'],
            'activated' => true,
            'error_code' => null,
        ];
    }

    /** @param array<string, mixed> $package */
    private function validPackageEnvelope(array $package): bool
    {
        return isset($package['documents'], $package['document_count'], $package['checksum'], $package['knowledge_version'])
            && is_array($package['documents'])
            && $package['documents'] !== []
            && is_int($package['document_count'])
            && $package['document_count'] === count($package['documents'])
            && is_string($package['checksum'])
            && preg_match('/^[a-f0-9]{64}$/', $package['checksum']) === 1
            && (is_int($package['knowledge_version']) || is_string($package['knowledge_version']))
            && strlen((string) $package['knowledge_version']) <= 32;
    }

    private function isSafelyRetryable(Response $response): bool
    {
        return in_array($response->status(), [408, 429], true) || $response->serverError();
    }

    /**
     * @param  mixed  $data
     * @param  array<string, mixed>  $package
     */
    private function validResponse(
        mixed $data,
        string $requestId,
        int $batchDocumentCount,
        array $package,
        bool $isFinalBatch,
    ): bool {
        if (! is_array($data)
            || count($data) !== 7
            || array_diff(array_keys($data), [
                'ok', 'request_id', 'accepted', 'rejected',
                'checksum', 'knowledge_version', 'activated',
            ]) !== []
            || ($data['ok'] ?? null) !== true
            || ($data['request_id'] ?? null) !== $requestId
            || ! is_int($data['accepted'] ?? null)
            || ! is_int($data['rejected'] ?? null)
            || $data['accepted'] !== $batchDocumentCount
            || $data['rejected'] !== 0
            || ($data['checksum'] ?? null) !== $package['checksum']
            || (string) ($data['knowledge_version'] ?? '') !== (string) $package['knowledge_version']
            || ! is_bool($data['activated'] ?? null)) {
            return false;
        }

        return $isFinalBatch ? $data['activated'] === true : $data['activated'] === false;
    }

    /**
     * @param  array<string, mixed>  $package
     * @return array{0: int, 1: int}
     */
    private function safePartialCounts(mixed $data, int $batchDocumentCount, string $requestId, array $package): array
    {
        if (! is_array($data)
            || ($data['request_id'] ?? null) !== $requestId
            || ($data['checksum'] ?? null) !== $package['checksum']
            || (string) ($data['knowledge_version'] ?? '') !== (string) $package['knowledge_version']
            || ! is_int($data['accepted'] ?? null)
            || ! is_int($data['rejected'] ?? null)
            || $data['accepted'] < 0
            || $data['rejected'] < 0
            || $data['accepted'] + $data['rejected'] > $batchDocumentCount) {
            return [0, 0];
        }

        return [$data['accepted'], $data['rejected']];
    }

    /** @return array<string, mixed> */
    private function failure(
        array $package,
        string $code,
        int $accepted = 0,
        int $rejected = 0,
        int $documentsSent = 0,
        int $batches = 0,
    ): array {
        return [
            'success' => false,
            'documents_sent' => $documentsSent,
            'accepted' => $accepted,
            'rejected' => $rejected,
            'batches' => $batches,
            'checksum' => $package['checksum'] ?? null,
            'knowledge_version' => $package['knowledge_version'] ?? null,
            'activated' => false,
            'error_code' => $code,
        ];
    }
}
