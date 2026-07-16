<?php

namespace App\Console\Commands;

use App\Services\Assistant\AssistantHmacSigner;
use App\Services\Assistant\AssistantN8nDocumentPackage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use JsonException;
use Symfony\Component\Process\Process;
use Throwable;

final class DiagnoseN8nIngest extends Command
{
    protected $signature = 'assistant:diagnose-n8n-ingest
        {--remote-test : Sends one synthetic diagnostic document only after explicit confirmation}
        {--confirm-remote-test : Confirms that a remote diagnostic can modify the configured RAG store}';

    protected $description = 'Diagnostica localmente el paquete y workflow de ingesta n8n sin enviar solicitudes remotas';

    public function handle(
        AssistantN8nDocumentPackage $documentPackage,
        AssistantHmacSigner $signer,
    ): int {
        $package = $this->loadPackage($documentPackage);
        $workflow = $this->inspectWorkflow();
        $sql = $this->inspectSqlSources();
        $urlConfigured = trim((string) config('assistant.n8n.ingest_webhook_url', '')) !== '';
        $secretConfigured = (string) config('assistant.n8n.ingest_secret', '') !== '';
        $timeout = (int) config('assistant.n8n.ingest_timeout_seconds', 30);

        $this->line('MediFlow n8n ingest diagnostic');
        $this->newLine();
        $this->line('Package:');
        $this->line('- Documents: '.($package['valid'] ? $package['document_count'] : 'invalid'));
        $this->line('- Batches: '.($package['valid'] ? (int) ceil($package['document_count'] / 10) : 'invalid'));
        $this->line('- Batch size: 10');
        $this->line('- Knowledge version: '.($package['valid'] ? $package['knowledge_version'] : 'invalid'));
        $this->line('- Checksum: '.($package['valid'] ? substr($package['checksum'], 0, 12).'...' : 'invalid'));
        $this->newLine();
        $this->line('Local configuration:');
        $this->line('- Remote enabled: '.(config('assistant.remote_enabled', false) ? 'yes' : 'no'));
        $this->line('- Ingest URL configured: '.($urlConfigured ? 'yes' : 'no'));
        $this->line('- HMAC secret configured: '.($secretConfigured ? 'yes' : 'no'));
        $this->line('- Provider: supabase');
        $this->line('- Remote assistant provider: '.((string) config('assistant.provider', '') ?: 'not configured'));
        $this->line('- Timeout valid: '.($timeout >= 5 && $timeout <= 60 ? 'yes' : 'no'));
        $this->newLine();
        $this->line('Workflow:');
        $this->line('- Gemini workflow present: '.($workflow['present'] ? 'yes' : 'no'));
        $this->line('- Structural validation: '.($workflow['valid'] ? 'passed' : 'failed'));
        $this->line('- Supabase host placeholder: '.($workflow['placeholder'] ? 'present' : 'missing'));
        $this->line('- No real Supabase host or embedded secret: '.($workflow['safe'] ? 'yes' : 'no'));
        $this->newLine();
        $this->line('SQL:');
        $this->line('- Main schema present: '.($sql['schema'] ? 'yes' : 'no'));
        $this->line('- Empty-table repair present: '.($sql['repair'] ? 'yes' : 'no'));

        $localValid = $package['valid']
            && $workflow['present']
            && $workflow['valid']
            && $workflow['safe']
            && $sql['schema']
            && $sql['repair'];

        if (! $this->option('remote-test')) {
            $this->newLine();
            $this->line('Remote requests performed: no');

            return $localValid ? self::SUCCESS : self::FAILURE;
        }

        if (! $this->option('confirm-remote-test')) {
            $this->error('Remote diagnostic requires --confirm-remote-test and may modify Supabase.');

            return self::FAILURE;
        }

        if (! $localValid || ! $urlConfigured || ! $secretConfigured) {
            $this->error('Remote diagnostic requires a valid local package, workflow, URL, and HMAC secret.');

            return self::FAILURE;
        }

        return $this->runRemoteTest($signer, $timeout);
        $this->warn('Remote diagnostic may modify Supabase and sends one synthetic document.');

    }

    /** @return array{valid: bool, document_count: int, knowledge_version: int|string, checksum: string} */
    private function loadPackage(AssistantN8nDocumentPackage $documentPackage): array
    {
        $path = (string) config('assistant.diagnostics.package_path', $documentPackage->path());
        $raw = is_file($path) ? file_get_contents($path) : false;

        if ($raw === false) {
            return ['valid' => false, 'document_count' => 0, 'knowledge_version' => 'invalid', 'checksum' => ''];
        }

        try {
            $package = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($package)) {
                throw new JsonException('Invalid package.');
            }
            $documentPackage->assertValid($package);

            return [
                'valid' => true,
                'document_count' => (int) $package['document_count'],
                'knowledge_version' => $package['knowledge_version'],
                'checksum' => (string) $package['checksum'],
            ];
        } catch (Throwable) {
            return ['valid' => false, 'document_count' => 0, 'knowledge_version' => 'invalid', 'checksum' => ''];
        }
    }

    /** @return array{present: bool, valid: bool, placeholder: bool, safe: bool} */
    private function inspectWorkflow(): array
    {
        $path = (string) config('assistant.diagnostics.workflow_path', base_path('n8n/workflows/mediflow-assistant-ingest-supabase-gemini.json'));
        $raw = is_file($path) ? file_get_contents($path) : false;
        if ($raw === false) {
            return ['present' => false, 'valid' => false, 'placeholder' => false, 'safe' => false];
        }

        try {
            $workflow = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return ['present' => true, 'valid' => false, 'placeholder' => false, 'safe' => false];
        }

        $placeholder = str_contains($raw, 'https://your-project.supabase.co')
            && substr_count($raw, '"name": "Ingest Endpoint Configuration"') === 1;
        $hasRealHost = preg_match('~https://(?!your-project\.supabase\.co)[a-z0-9-]+\.supabase\.co~i', $raw) === 1;
        $hasSecretLiteral = preg_match('~(?:sk-[a-z0-9_-]{16,}|AIza[0-9A-Za-z_-]{20,}|service[_ -]?role)~i', $raw) === 1;
        $looksImportable = is_array($workflow)
            && is_array($workflow['nodes'] ?? null)
            && is_array($workflow['connections'] ?? null);
        $validatorPasses = false;

        if ($looksImportable && $path === base_path('n8n/workflows/mediflow-assistant-ingest-supabase-gemini.json')) {
            $process = new Process(['node', 'scripts/validate-n8n-assistant-workflows.mjs'], base_path());
            $process->setTimeout(30);
            $process->run();
            $validatorPasses = $process->isSuccessful();
        }

        return [
            'present' => true,
            'valid' => $looksImportable && $validatorPasses,
            'placeholder' => $placeholder,
            'safe' => ! $hasRealHost && ! $hasSecretLiteral,
        ];
    }

    /** @return array{schema: bool, repair: bool} */
    private function inspectSqlSources(): array
    {
        return [
            'schema' => is_file((string) config('assistant.diagnostics.schema_path', base_path('n8n/supabase/assistant-rag-schema.sql'))),
            'repair' => is_file((string) config('assistant.diagnostics.repair_path', base_path('n8n/supabase/repair-empty-gemini-rag.sql'))),
        ];
    }

    private function runRemoteTest(AssistantHmacSigner $signer, int $timeout): int
    {
        $requestId = Str::uuid()->toString();
        $timestamp = now('UTC')->toIso8601String();
        $payload = [
            'request_id' => $requestId,
            'provider' => 'supabase',
            'batch_index' => 0,
            'batch_count' => 1,
            'full_manifest' => false,
            'checksum' => hash('sha256', 'mediflow-n8n-diagnostic'),
            'knowledge_version' => 2,
            'document_count' => 1,
            'documents' => [[
                'document_id' => 'diagnostic:system:v2',
                'content' => 'Synthetic MediFlow diagnostic document.',
                'metadata' => ['role' => 'system', 'locale' => 'es-EC', 'source' => 'diagnostic'],
            ]],
            'timestamp' => $timestamp,
        ];
        $secret = (string) config('assistant.n8n.ingest_secret');
        $body = $signer->encode($payload);

        try {
            $response = Http::timeout($timeout)
                ->acceptJson()
                ->withHeaders($signer->headers($requestId, $timestamp, $body, $secret, 2))
                ->withBody($body, 'application/json')
                ->post((string) config('assistant.n8n.ingest_webhook_url'));
        } catch (Throwable) {
            $this->error('Remote diagnostic failed without exposing transport details.');

            return self::FAILURE;
        }

        $data = $response->json();
        $expected = ['ok', 'request_id', 'accepted', 'rejected', 'checksum', 'knowledge_version', 'activated'];
        if (! $response->successful() || ! is_array($data) || array_keys($data) !== $expected) {
            $this->error('Remote diagnostic returned an invalid safe response.');

            return self::FAILURE;
        }

        $this->info('Remote diagnostic completed with one synthetic document.');

        return self::SUCCESS;
    }
}
