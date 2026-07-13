<?php

namespace App\Services\Assistant;

use JsonException;

final class AssistantHmacSigner
{
    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws JsonException
     */
    public function encode(array $payload): string
    {
        return json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );
    }

    public function sign(string $timestamp, string $rawBody, string $secret): string
    {
        return hash_hmac('sha256', $timestamp.'.'.$rawBody, $secret);
    }

    /**
     * @return array<string, string>
     */
    public function headers(
        string $requestId,
        string $timestamp,
        string $rawBody,
        string $secret,
        int|string|null $knowledgeVersion,
    ): array {
        return [
            'X-MediFlow-Request-Id' => $requestId,
            'X-MediFlow-Timestamp' => $timestamp,
            'X-MediFlow-Signature' => $this->sign($timestamp, $rawBody, $secret),
            'X-MediFlow-Assistant-Version' => (string) ($knowledgeVersion ?? 'unknown'),
        ];
    }
}
