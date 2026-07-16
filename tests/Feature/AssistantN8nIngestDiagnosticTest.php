<?php

namespace Tests\Feature;

use Illuminate\Console\Command;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AssistantN8nIngestDiagnosticTest extends TestCase
{
    private const URL = 'https://n8n.example.test/webhook/assistant-ingest';

    private const SECRET = 'diagnostic-test-secret';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        config([
            'assistant.remote_enabled' => false,
            'assistant.provider' => null,
            'assistant.n8n.ingest_webhook_url' => self::URL,
            'assistant.n8n.ingest_secret' => self::SECRET,
            'assistant.n8n.ingest_timeout_seconds' => 30,
            'assistant.diagnostics.package_path' => base_path('n8n/knowledge/assistant-documents.json'),
            'assistant.diagnostics.workflow_path' => base_path('n8n/workflows/mediflow-assistant-ingest-supabase-gemini.json'),
            'assistant.diagnostics.schema_path' => base_path('n8n/supabase/assistant-rag-schema.sql'),
            'assistant.diagnostics.repair_path' => base_path('n8n/supabase/repair-empty-gemini-rag.sql'),
        ]);
    }

    public function test_local_diagnostic_validates_the_package_workflow_and_sql_without_http(): void
    {
        Http::fake();

        $exitCode = Artisan::call('assistant:diagnose-n8n-ingest');
        $output = Artisan::output();

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Documents: 161', $output);
        $this->assertStringContainsString('Batches: 17', $output);
        $this->assertStringContainsString('Knowledge version: 2', $output);
        $this->assertStringContainsString('Structural validation: passed', $output);
        $this->assertStringContainsString('Supabase host placeholder: present', $output);
        $this->assertStringContainsString('Remote requests performed: no', $output);
        Http::assertNothingSent();
    }

    public function test_diagnostic_reports_missing_url_and_secret_without_exposing_values(): void
    {
        Http::fake();
        config(['assistant.n8n.ingest_webhook_url' => '', 'assistant.n8n.ingest_secret' => '']);

        $exitCode = Artisan::call('assistant:diagnose-n8n-ingest');
        $output = Artisan::output();

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Ingest URL configured: no', $output);
        $this->assertStringContainsString('HMAC secret configured: no', $output);
        $this->assertStringNotContainsString(self::URL, $output);
        $this->assertStringNotContainsString(self::SECRET, $output);
        Http::assertNothingSent();
    }

    public function test_diagnostic_fails_for_a_missing_or_invalid_workflow(): void
    {
        config(['assistant.diagnostics.workflow_path' => base_path('n8n/workflows/missing-gemini.json')]);

        $missingExit = Artisan::call('assistant:diagnose-n8n-ingest');
        $this->assertSame(Command::FAILURE, $missingExit);
        $this->assertStringContainsString('Gemini workflow present: no', Artisan::output());

        config(['assistant.diagnostics.workflow_path' => resource_path('assistant/knowledge-base.json')]);
        $invalidExit = Artisan::call('assistant:diagnose-n8n-ingest');
        $this->assertSame(Command::FAILURE, $invalidExit);
        $this->assertStringContainsString('Structural validation: failed', Artisan::output());
    }

    public function test_diagnostic_fails_for_an_invalid_package_and_never_prints_documents(): void
    {
        config(['assistant.diagnostics.package_path' => resource_path('assistant/knowledge-base.json')]);

        $exitCode = Artisan::call('assistant:diagnose-n8n-ingest');
        $output = Artisan::output();

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('Documents: invalid', $output);
        $this->assertStringNotContainsString('Titulo:', $output);
        $this->assertStringNotContainsString('title', $output);
    }

    public function test_remote_test_requires_confirmation_and_complete_configuration(): void
    {
        Http::fake();

        $unconfirmed = Artisan::call('assistant:diagnose-n8n-ingest', ['--remote-test' => true]);
        $this->assertSame(Command::FAILURE, $unconfirmed);
        $this->assertStringContainsString('--confirm-remote-test', Artisan::output());
        Http::assertNothingSent();

        config(['assistant.n8n.ingest_secret' => '']);
        $incomplete = Artisan::call('assistant:diagnose-n8n-ingest', [
            '--remote-test' => true,
            '--confirm-remote-test' => true,
        ]);
        $this->assertSame(Command::FAILURE, $incomplete);
        $this->assertStringContainsString('URL, and HMAC secret', Artisan::output());
        Http::assertNothingSent();
    }

    public function test_confirmed_remote_test_uses_one_synthetic_document_and_safe_output(): void
    {
        Http::fake(function (Request $request) {
            $payload = $request->data();

            return Http::response([
                'ok' => true,
                'request_id' => $payload['request_id'],
                'accepted' => 1,
                'rejected' => 0,
                'checksum' => $payload['checksum'],
                'knowledge_version' => 2,
                'activated' => false,
            ]);
        });

        $exitCode = Artisan::call('assistant:diagnose-n8n-ingest', [
            '--remote-test' => true,
            '--confirm-remote-test' => true,
        ]);
        $output = Artisan::output();

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('one synthetic document', $output);
        $this->assertStringNotContainsString(self::URL, $output);
        $this->assertStringNotContainsString(self::SECRET, $output);
        Http::assertSent(function (Request $request): bool {
            $payload = $request->data();

            return count($payload['documents']) === 1
                && $payload['documents'][0]['document_id'] === 'diagnostic:system:v2'
                && ! array_key_exists('user_id', $payload)
                && ! array_key_exists('clinic_id', $payload);
        });
    }
}
