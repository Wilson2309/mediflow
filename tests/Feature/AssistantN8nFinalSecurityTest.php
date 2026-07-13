<?php

namespace Tests\Feature;

use App\Data\AssistantContext;
use App\Services\Assistant\AssistantN8nDocumentPackage;
use App\Services\Assistant\N8nAssistantProvider;
use App\Services\Assistant\N8nKnowledgeSyncService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use JsonException;
use Tests\TestCase;

class AssistantN8nFinalSecurityTest extends TestCase
{
    private const QUERY_URL = 'https://n8n.example.test/webhook/assistant-query';

    private const INGEST_URL = 'https://n8n.example.test/webhook/assistant-ingest';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        config([
            'assistant.n8n.webhook_url' => self::QUERY_URL,
            'assistant.n8n.secret' => 'query-test-secret',
            'assistant.n8n.ingest_webhook_url' => self::INGEST_URL,
            'assistant.n8n.ingest_secret' => 'ingest-test-secret',
        ]);
    }

    /** @throws JsonException */
    public function test_document_package_contains_the_exact_entry_and_role_pairs(): void
    {
        $knowledge = json_decode(
            (string) file_get_contents(resource_path('assistant/knowledge-base.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $expected = [];
        foreach ($knowledge['entries'] as $entry) {
            $version = $entry['version'] ?? $knowledge['defaults']['version'];
            foreach ($entry['roles'] as $role) {
                $expected[] = $entry['id'].':'.$role.':v'.$version;
            }
        }
        sort($expected, SORT_STRING);

        $actual = array_column(
            app(AssistantN8nDocumentPackage::class)->load()['documents'],
            'document_id',
        );

        $this->assertSame($expected, $actual);
    }

    public function test_query_response_requires_native_json_types_and_nonempty_clean_text(): void
    {
        $base = [
            'answer' => 'Respuesta sintetica segura.',
            'confidence' => 0.9,
            'steps' => ['Abre el modulo autorizado.'],
            'suggestions' => [],
            'can_escalate' => false,
        ];
        $responses = [
            [...$base, 'confidence' => '0.9'],
            [...$base, 'can_escalate' => 1],
            [...$base, 'steps' => ["\x01"]],
        ];
        Http::fake(function () use (&$responses) {
            return Http::response(array_shift($responses));
        });

        $provider = app(N8nAssistantProvider::class);
        foreach (range(1, 3) as $index) {
            $result = $provider->answer($this->context('00000000-0000-4000-8000-00000000000'.$index));

            $this->assertTrue($result->fallbackUsed);
            $this->assertSame('INVALID_REMOTE_RESPONSE', $result->code);
        }
        Http::assertSentCount(3);
    }

    public function test_uncorrelated_partial_ingest_response_cannot_forge_summary_counts(): void
    {
        Http::fake(function (Request $request) {
            $payload = $request->data();

            return Http::response([
                'ok' => true,
                'request_id' => '00000000-0000-4000-8000-000000000099',
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
        $this->assertSame('INVALID_INGEST_RESPONSE', $result['error_code']);
        $this->assertSame(0, $result['accepted']);
        $this->assertSame(0, $result['rejected']);
        $this->assertSame(100, $result['documents_sent']);
    }

    private function context(string $requestId): AssistantContext
    {
        return new AssistantContext(
            requestId: $requestId,
            question: 'Como uso el modulo de recetas',
            canonicalRole: 'medico',
            currentModule: 'prescriptions',
            currentRoute: 'prescriptions.index',
            connectionState: 'ONLINE',
            locale: 'es-EC',
            knowledgeVersion: 2,
            timestamp: '2026-07-13T00:00:00Z',
        );
    }
}
