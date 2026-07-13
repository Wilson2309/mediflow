<?php

namespace Tests\Feature;

use App\Data\AssistantResult;
use App\Services\Assistant\AssistantHmacSigner;
use App\Services\Assistant\AssistantN8nDocumentPackage;
use App\Services\Assistant\N8nKnowledgeSyncService;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use JsonException;
use Tests\TestCase;

class AssistantN8nPhaseFourTest extends TestCase
{
    private const INGEST_URL = 'https://n8n.example.test/webhook/assistant-ingest';

    private const QUERY_URL = 'https://n8n.example.test/webhook/assistant-query';

    private const INGEST_SECRET = 'phase-four-ingest-test-secret';

    private const QUERY_SECRET = 'phase-four-query-test-secret';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        config([
            'assistant.remote_enabled' => false,
            'assistant.n8n.webhook_url' => self::QUERY_URL,
            'assistant.n8n.secret' => self::QUERY_SECRET,
            'assistant.timeout_seconds' => 8,
            'assistant.n8n.ingest_webhook_url' => self::INGEST_URL,
            'assistant.n8n.ingest_secret' => self::INGEST_SECRET,
            'assistant.n8n.ingest_timeout_seconds' => 30,
        ]);
    }

    /** @throws JsonException */
    public function test_document_package_expands_every_entry_by_authorized_role_with_safe_metadata(): void
    {
        $package = app(AssistantN8nDocumentPackage::class)->load();
        $knowledge = json_decode(
            (string) file_get_contents(resource_path('assistant/knowledge-base.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $expectedCount = array_sum(array_map(
            static fn (array $entry): int => count($entry['roles']),
            $knowledge['entries'],
        ));
        $expectedRoleCounts = [
            'administrador' => 62,
            'recepcionista' => 31,
            'caja_finanzas' => 21,
            'medico' => 33,
            'super_admin' => 14,
        ];
        $actualRoleCounts = array_fill_keys(array_keys($expectedRoleCounts), 0);
        $documentIds = [];

        $this->assertSame(161, $expectedCount);
        $this->assertSame($expectedCount, $package['document_count']);
        $this->assertCount($expectedCount, $package['documents']);
        $this->assertSame('resources/assistant/knowledge-base.json', $package['source']);
        $this->assertSame('sha256', $package['checksum_algorithm']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $package['checksum']);

        foreach ($package['documents'] as $document) {
            $metadata = $document['metadata'];
            $role = $metadata['role'];

            $this->assertArrayNotHasKey($document['document_id'], $documentIds);
            $documentIds[$document['document_id']] = true;
            $actualRoleCounts[$role]++;

            $this->assertSame(
                $metadata['entry_id'].':'.$role.':v'.$metadata['knowledge_version'],
                $document['document_id'],
            );
            $this->assertSame('es-EC', $metadata['locale']);
            $this->assertSame('active', $metadata['status']);
            $this->assertSame('knowledge-base.json', $metadata['source']);
            $this->assertNotEmpty($metadata['modules']);
            $this->assertStringContainsString('Título:', $document['content']);
            $this->assertStringContainsString('Pregunta:', $document['content']);
            $this->assertStringContainsString('Respuesta:', $document['content']);
            $this->assertDoesNotMatchRegularExpression(
                '~(?:resources/|app/|database/|routes/|\.php\b)~iu',
                $document['content'],
            );

            foreach ($metadata['modules'] as $module) {
                $this->assertContains($role, $knowledge['catalogs']['modules'][$module]['roles']);
            }

            foreach ($knowledge['catalogs']['forbidden_phrases'] as $phrase) {
                $this->assertStringNotContainsStringIgnoringCase($phrase, $document['content']);
            }
        }

        $checksumPayload = [
            'knowledge_schema_version' => $package['knowledge_schema_version'],
            'documents' => $package['documents'],
        ];
        $encoded = json_encode(
            $checksumPayload,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );

        $this->assertSame($expectedRoleCounts, $actualRoleCounts);
        $this->assertSame(hash('sha256', $encoded), $package['checksum']);
    }

    public function test_hmac_signer_matches_the_raw_json_contract(): void
    {
        $signer = app(AssistantHmacSigner::class);
        $timestamp = '2026-07-13T00:00:00Z';
        $payload = ['request_id' => 'synthetic-request', 'question' => '¿Cómo uso MediFlow?'];
        $body = $signer->encode($payload);
        $headers = $signer->headers('synthetic-request', $timestamp, $body, self::QUERY_SECRET, 2);

        $this->assertSame('{"request_id":"synthetic-request","question":"¿Cómo uso MediFlow?"}', $body);
        $this->assertSame(
            hash_hmac('sha256', $timestamp.'.'.$body, self::QUERY_SECRET),
            $headers['X-MediFlow-Signature'],
        );
        $this->assertSame('synthetic-request', $headers['X-MediFlow-Request-Id']);
        $this->assertSame($timestamp, $headers['X-MediFlow-Timestamp']);
        $this->assertSame('2', $headers['X-MediFlow-Assistant-Version']);
    }

    public function test_sync_dry_run_validates_package_without_network_or_secrets_in_output(): void
    {
        Http::fake();

        $exitCode = Artisan::call('assistant:sync-n8n-knowledge', [
            '--dry-run' => true,
            '--provider' => 'supabase',
            '--batch' => 10,
        ]);
        $output = Artisan::output();

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Dry-run completado', $output);
        $this->assertStringContainsString('161', $output);
        $this->assertStringNotContainsString(self::INGEST_SECRET, $output);
        $this->assertStringNotContainsString(self::INGEST_URL, $output);
        $this->assertStringNotContainsString('Título:', $output);
        Http::assertNothingSent();
    }

    public function test_sync_command_sends_signed_minimal_batches_and_confirms_activation(): void
    {
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

        $exitCode = Artisan::call('assistant:sync-n8n-knowledge', [
            '--provider' => 'supabase',
            '--batch' => 100,
        ]);
        $output = Artisan::output();

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Sincronización completada', $output);
        $this->assertStringNotContainsString(self::INGEST_SECRET, $output);
        $this->assertStringNotContainsString(self::INGEST_URL, $output);
        Http::assertSentCount(2);

        $requests = Http::recorded()->map(fn (array $exchange): Request => $exchange[0])->values();
        $this->assertSame([0, 1], $requests->map(fn (Request $request): int => $request->data()['batch_index'])->all());
        $this->assertSame(161, $requests->sum(fn (Request $request): int => count($request->data()['documents'])));

        foreach ($requests as $request) {
            $payload = $request->data();
            $timestamp = $request->header('X-MediFlow-Timestamp')[0] ?? '';
            $requestId = $request->header('X-MediFlow-Request-Id')[0] ?? '';
            $signature = $request->header('X-MediFlow-Signature')[0] ?? '';

            $this->assertSame(self::INGEST_URL, $request->url());
            $this->assertSame('POST', $request->method());
            $this->assertTrue(Str::isUuid($requestId));
            $this->assertSame($payload['request_id'], $requestId);
            $this->assertSame($payload['timestamp'], $timestamp);
            $this->assertSame(2, $payload['batch_count']);
            $this->assertTrue($payload['full_manifest']);
            $this->assertSame('supabase', $payload['provider']);
            $this->assertSame(
                hash_hmac('sha256', $timestamp.'.'.$request->body(), self::INGEST_SECRET),
                $signature,
            );
            $this->assertSame(
                ['request_id', 'provider', 'batch_index', 'batch_count', 'full_manifest', 'checksum', 'knowledge_version', 'document_count', 'documents', 'timestamp'],
                array_keys($payload),
            );
            $this->assertStringNotContainsString(self::INGEST_SECRET, $request->body());

            foreach ($payload['documents'] as $document) {
                foreach (['user_id', 'clinic_id', 'name', 'email', 'patient_id', 'payment_id'] as $forbidden) {
                    $this->assertArrayNotHasKey($forbidden, $document);
                    $this->assertArrayNotHasKey($forbidden, $document['metadata']);
                }
            }
        }
    }

    public function test_partial_response_and_timeout_fail_closed_without_real_requests(): void
    {
        $package = app(AssistantN8nDocumentPackage::class)->load();
        $service = app(N8nKnowledgeSyncService::class);

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

        $partial = $service->sync($package, 'supabase', 100);
        $this->assertFalse($partial['success']);
        $this->assertFalse($partial['activated']);
        $this->assertSame('INVALID_INGEST_RESPONSE', $partial['error_code']);
        Http::assertSentCount(1);

        $attempts = 0;
        Http::fake(function () use (&$attempts) {
            $attempts++;

            throw new ConnectionException('synthetic timeout');
        });
        $timeout = $service->sync($package, 'supabase', 100);

        $this->assertFalse($timeout['success']);
        $this->assertFalse($timeout['activated']);
        $this->assertSame('INGEST_CONNECTION_FAILED', $timeout['error_code']);
        $this->assertSame(2, $attempts);
    }

    public function test_connection_command_uses_synthetic_context_while_remote_widget_stays_disabled(): void
    {
        Http::fake([self::QUERY_URL => Http::response([
            'answer' => 'Abre Consultas y selecciona la opción de receta disponible.',
            'confidence' => 0.93,
            'steps' => ['Abre una consulta autorizada.', 'Selecciona Crear receta.'],
            'suggestions' => ['¿Cómo firmo la receta?'],
            'can_escalate' => false,
        ])]);

        $exitCode = Artisan::call('assistant:test-n8n', [
            '--expect-remote' => true,
            '--show-metadata' => true,
        ]);
        $output = Artisan::output();

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertFalse(config('assistant.remote_enabled'));
        $this->assertStringContainsString('Status: REMOTE', $output);
        $this->assertStringContainsString('Request ID:', $output);
        $this->assertStringContainsString('Source: remote', $output);
        $this->assertStringContainsString('Confidence: 0.93', $output);
        $this->assertStringContainsString('Fallback: no', $output);
        $this->assertStringNotContainsString(self::QUERY_SECRET, $output);
        $this->assertStringNotContainsString(self::QUERY_URL, $output);

        Http::assertSent(function (Request $request): bool {
            $payload = $request->data();

            return $request->url() === self::QUERY_URL
                && $payload['question'] === '¿Cómo puedo crear una receta médica?'
                && $payload['role'] === 'medico'
                && $payload['module'] === 'prescriptions'
                && ! array_key_exists('user_id', $payload)
                && ! array_key_exists('clinic_id', $payload);
        });
    }

    public function test_connection_command_rejects_sensitive_question_and_remote_fallback(): void
    {
        Http::fake();
        $sensitiveExit = Artisan::call('assistant:test-n8n', [
            '--question' => 'Ayuda para paciente@example.com',
        ]);

        $this->assertSame(Command::INVALID, $sensitiveExit);
        $this->assertStringContainsString('información sensible', Artisan::output());
        Http::assertNothingSent();

        Http::fake([self::QUERY_URL => Http::response([
            'answer' => AssistantResult::FALLBACK_ANSWER,
            'confidence' => 0,
            'steps' => [],
            'suggestions' => [],
            'can_escalate' => true,
        ])]);

        $fallbackExit = Artisan::call('assistant:test-n8n', ['--expect-remote' => true]);
        $output = Artisan::output();

        $this->assertSame(Command::FAILURE, $fallbackExit);
        $this->assertStringContainsString('Status: FALLBACK', $output);
        $this->assertStringContainsString('No se recibió la respuesta remota esperada', $output);
        $this->assertStringNotContainsString(self::QUERY_SECRET, $output);
        Http::assertSentCount(1);
    }
}
