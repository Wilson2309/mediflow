<?php

namespace Tests\Feature;

use App\Services\Assistant\AssistantN8nDocumentPackage;
use App\Services\Assistant\N8nKnowledgeSyncService;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AssistantN8nIngestFailureTest extends TestCase
{
    private const INGEST_URL = 'https://n8n.example.test/webhook/assistant-ingest';

    private const INGEST_SECRET = 'ingest-failure-test-secret';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        config([
            'assistant.n8n.ingest_webhook_url' => self::INGEST_URL,
            'assistant.n8n.ingest_secret' => self::INGEST_SECRET,
            'assistant.n8n.ingest_timeout_seconds' => 30,
        ]);
    }

    public function test_failed_n8n_response_never_increases_accepted_documents(): void
    {
        $package = app(AssistantN8nDocumentPackage::class)->load();
        Http::fake(function (Request $request) {
            $payload = $request->data();

            return Http::response([
                'ok' => false,
                'request_id' => $payload['request_id'],
                'accepted' => 0,
                'rejected' => count($payload['documents']),
                'checksum' => $payload['checksum'],
                'knowledge_version' => $payload['knowledge_version'],
                'activated' => false,
            ]);
        });

        $result = app(N8nKnowledgeSyncService::class)->sync($package, 'supabase', 100);

        $this->assertFalse($result['success']);
        $this->assertSame('INGEST_BATCH_REJECTED', $result['error_code']);
        $this->assertSame(0, $result['accepted']);
        $this->assertSame(100, $result['rejected']);
        $this->assertFalse($result['activated']);
    }

    public function test_partial_success_response_is_not_accepted_as_a_valid_batch(): void
    {
        $package = app(AssistantN8nDocumentPackage::class)->load();
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

        $result = app(N8nKnowledgeSyncService::class)->sync($package, 'supabase', 100);

        $this->assertFalse($result['success']);
        $this->assertSame('INGEST_BATCH_REJECTED', $result['error_code']);
        $this->assertSame(0, $result['accepted']);
        $this->assertSame(0, $result['rejected']);
    }

    public function test_additional_response_field_is_a_shape_error_and_never_counts_acceptance(): void
    {
        $package = app(AssistantN8nDocumentPackage::class)->load();
        Http::fake(function (Request $request) {
            $payload = $request->data();

            return Http::response([
                'ok' => true,
                'request_id' => $payload['request_id'],
                'accepted' => count($payload['documents']),
                'rejected' => 0,
                'checksum' => $payload['checksum'],
                'knowledge_version' => $payload['knowledge_version'],
                'activated' => false,
                'unexpected' => true,
            ]);
        });

        $result = app(N8nKnowledgeSyncService::class)->sync($package, 'supabase', 100);

        $this->assertFalse($result['success']);
        $this->assertSame('INGEST_INVALID_RESPONSE_SHAPE', $result['error_code']);
        $this->assertSame(0, $result['accepted']);
    }

    public function test_numeric_strings_are_rejected_as_invalid_response_types(): void
    {
        $package = app(AssistantN8nDocumentPackage::class)->load();
        Http::fake(function (Request $request) {
            $payload = $request->data();

            return Http::response([
                'ok' => true,
                'request_id' => $payload['request_id'],
                'accepted' => (string) count($payload['documents']),
                'rejected' => 0,
                'checksum' => $payload['checksum'],
                'knowledge_version' => $payload['knowledge_version'],
                'activated' => false,
            ]);
        });

        $result = app(N8nKnowledgeSyncService::class)->sync($package, 'supabase', 100);

        $this->assertFalse($result['success']);
        $this->assertSame('INGEST_INVALID_RESPONSE_TYPES', $result['error_code']);
        $this->assertSame(0, $result['accepted']);
    }

    public function test_final_success_without_manifest_activation_is_reported_separately(): void
    {
        $package = app(AssistantN8nDocumentPackage::class)->load();
        Http::fake(function (Request $request) {
            $payload = $request->data();

            return Http::response([
                'ok' => true,
                'request_id' => $payload['request_id'],
                'accepted' => count($payload['documents']),
                'rejected' => 0,
                'checksum' => $payload['checksum'],
                'knowledge_version' => $payload['knowledge_version'],
                'activated' => false,
            ]);
        });

        $result = app(N8nKnowledgeSyncService::class)->sync($package, 'supabase', 100);

        $this->assertFalse($result['success']);
        $this->assertSame('INGEST_MANIFEST_NOT_ACTIVATED', $result['error_code']);
        $this->assertSame(100, $result['accepted']);
    }

    public function test_intermediate_batch_cannot_activate_the_manifest(): void
    {
        $package = app(AssistantN8nDocumentPackage::class)->load();
        Http::fake(function (Request $request) {
            $payload = $request->data();

            return Http::response([
                'ok' => true,
                'request_id' => $payload['request_id'],
                'accepted' => count($payload['documents']),
                'rejected' => 0,
                'checksum' => $payload['checksum'],
                'knowledge_version' => $payload['knowledge_version'],
                'activated' => true,
            ]);
        });

        $result = app(N8nKnowledgeSyncService::class)->sync($package, 'supabase', 100);

        $this->assertFalse($result['success']);
        $this->assertSame('INGEST_BATCH_REJECTED', $result['error_code']);
        $this->assertSame(0, $result['accepted']);
    }

    public function test_confirmed_retry_response_for_existing_documents_remains_valid(): void
    {
        $package = app(AssistantN8nDocumentPackage::class)->load();
        Http::fake(function (Request $request) {
            $payload = $request->data();

            return Http::response([
                'ok' => true,
                'request_id' => $payload['request_id'],
                'accepted' => count($payload['documents']),
                'rejected' => 0,
                'checksum' => $payload['checksum'],
                'knowledge_version' => $payload['knowledge_version'],
                'activated' => $payload['batch_index'] === $payload['batch_count'] - 1,
            ]);
        });

        $result = app(N8nKnowledgeSyncService::class)->sync($package, 'supabase', 100);

        $this->assertTrue($result['success']);
        $this->assertSame($package['document_count'], $result['accepted']);
        $this->assertTrue($result['activated']);
    }

    public function test_command_reports_the_failed_batch_without_documents_or_secrets(): void
    {
        Http::fake(function (Request $request) {
            $payload = $request->data();

            return Http::response([
                'ok' => false,
                'request_id' => $payload['request_id'],
                'accepted' => 0,
                'rejected' => count($payload['documents']),
                'checksum' => $payload['checksum'],
                'knowledge_version' => $payload['knowledge_version'],
                'activated' => false,
            ]);
        });

        $exitCode = Artisan::call('assistant:sync-n8n-knowledge', [
            '--provider' => 'supabase',
            '--batch' => 10,
        ]);
        $output = Artisan::output();

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('INGEST_BATCH_REJECTED', $output);
        $this->assertStringContainsString('Lote fallido: 1', $output);
        $this->assertStringContainsString('Aceptados antes del fallo: 0', $output);
        $this->assertStringContainsString('Rechazados en el lote fallido: 10', $output);
        $this->assertStringNotContainsString(self::INGEST_SECRET, $output);
        $this->assertStringNotContainsString(self::INGEST_URL, $output);
        $this->assertStringNotContainsString('Título:', $output);
    }
}
