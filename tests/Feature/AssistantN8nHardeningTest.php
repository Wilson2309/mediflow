<?php

namespace Tests\Feature;

use App\Data\AssistantContext;
use App\Services\Assistant\AssistantN8nDocumentPackage;
use App\Services\Assistant\N8nAssistantProvider;
use App\Services\Assistant\N8nKnowledgeSyncService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use JsonException;
use RuntimeException;
use Tests\TestCase;

class AssistantN8nHardeningTest extends TestCase
{
    private const INGEST_URL = 'https://n8n.example.test/webhook/assistant-ingest';

    private const QUERY_URL = 'https://n8n.example.test/webhook/assistant-query';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        config([
            'assistant.n8n.webhook_url' => self::QUERY_URL,
            'assistant.n8n.secret' => 'query-test-secret',
            'assistant.n8n.ingest_webhook_url' => self::INGEST_URL,
            'assistant.n8n.ingest_secret' => 'ingest-test-secret',
            'assistant.n8n.ingest_timeout_seconds' => 30,
        ]);
    }

    /** @throws JsonException */
    public function test_package_rejects_tampered_authoritative_document_even_with_recomputed_checksum(): void
    {
        $validator = app(AssistantN8nDocumentPackage::class);
        $package = $validator->load();
        $package['documents'][0]['content'] .= "\nAlterado";
        $package['checksum'] = hash('sha256', json_encode([
            'knowledge_schema_version' => $package['knowledge_schema_version'],
            'documents' => $package['documents'],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        $this->expectException(RuntimeException::class);
        $validator->assertValid($package);
    }

    /** @throws JsonException */
    public function test_sync_rejects_empty_or_tampered_package_without_a_request(): void
    {
        Http::fake();
        $service = app(N8nKnowledgeSyncService::class);
        $invalid = [
            'documents' => [],
            'document_count' => 0,
            'checksum' => str_repeat('a', 64),
            'knowledge_version' => 1,
        ];

        $result = $service->sync($invalid, 'supabase', 10);

        $this->assertFalse($result['success']);
        $this->assertFalse($result['activated']);
        $this->assertSame('INVALID_INGEST_PACKAGE', $result['error_code']);
        Http::assertNothingSent();

        $tampered = app(AssistantN8nDocumentPackage::class)->load();
        $tampered['documents'][0]['metadata']['role'] = 'super_admin';
        $tampered['checksum'] = hash('sha256', json_encode([
            'knowledge_schema_version' => $tampered['knowledge_schema_version'],
            'documents' => $tampered['documents'],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        $result = $service->sync($tampered, 'supabase', 10);

        $this->assertFalse($result['success']);
        $this->assertSame('INVALID_INGEST_PACKAGE', $result['error_code']);
        Http::assertNothingSent();
    }

    public function test_simple_provider_does_not_retry_an_ambiguous_connection_failure(): void
    {
        $attempts = 0;
        Http::fake(function () use (&$attempts) {
            $attempts++;

            throw new ConnectionException('synthetic timeout');
        });

        $result = app(N8nKnowledgeSyncService::class)->sync(
            app(AssistantN8nDocumentPackage::class)->load(),
            'simple',
            100,
        );

        $this->assertFalse($result['success']);
        $this->assertSame('INGEST_CONNECTION_FAILED', $result['error_code']);
        $this->assertSame(1, $attempts);
        $this->assertSame(100, $result['documents_sent']);
        $this->assertSame(1, $result['batches']);
    }

    public function test_partial_ingest_failure_preserves_safe_technical_counts(): void
    {
        Http::fake(function (Request $request) {
            $payload = $request->data();

            return Http::response([
                'ok' => true,
                'request_id' => $payload['request_id'],
                'accepted' => count($payload['documents']) - 1,
                'rejected' => 1,
                'checksum' => $payload['checksum'],
                'knowledge_version' => $payload['knowledge_version'],
                'activated' => false,
            ]);
        });

        $result = app(N8nKnowledgeSyncService::class)->sync(
            app(AssistantN8nDocumentPackage::class)->load(),
            'supabase',
            100,
        );

        $this->assertFalse($result['success']);
        $this->assertSame('INGEST_BATCH_REJECTED', $result['error_code']);
        $this->assertSame(100, $result['documents_sent']);
        $this->assertSame(0, $result['accepted']);
        $this->assertSame(0, $result['rejected']);
        $this->assertSame(1, $result['batches']);
    }

    public function test_query_provider_rejects_response_missing_the_exact_five_fields(): void
    {
        Http::fake([self::QUERY_URL => Http::response([
            'answer' => 'Respuesta que no cumple el contrato completo.',
        ])]);
        $context = new AssistantContext(
            requestId: '00000000-0000-4000-8000-000000000001',
            question: 'Como se usa el modulo de recetas',
            canonicalRole: 'medico',
            currentModule: 'prescriptions',
            currentRoute: 'prescriptions.index',
            connectionState: 'ONLINE',
            locale: 'es-EC',
            knowledgeVersion: 1,
            timestamp: '2026-07-13T00:00:00Z',
        );

        $result = app(N8nAssistantProvider::class)->answer($context);

        $this->assertTrue($result->fallbackUsed);
        $this->assertSame('fallback', $result->source);
        $this->assertSame('INVALID_REMOTE_RESPONSE', $result->code);
        Http::assertSentCount(1);
    }
}
