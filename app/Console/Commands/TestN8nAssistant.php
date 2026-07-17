<?php

namespace App\Console\Commands;

use App\Data\AssistantContext;
use App\Services\Assistant\AssistantKnowledgeCatalog;
use App\Services\Assistant\AssistantSensitiveContentDetector;
use App\Services\Assistant\N8nAssistantProvider;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use JsonException;

final class TestN8nAssistant extends Command
{
    protected $signature = 'assistant:test-n8n
        {--question=¿Cómo puedo crear una receta médica? : Pregunta exclusivamente sintética}
        {--role=medico : Rol canónico sintético}
        {--module=prescriptions : Módulo autorizado para el rol}
        {--expect-remote : Falla si n8n no devuelve una respuesta remota válida}
        {--show-metadata : Muestra únicamente metadata técnica segura}
        {--force : Confirma explícitamente una prueba en producción}';

    protected $description = 'Prueba el proveedor n8n con contexto sintético y sin activar el widget remoto';

    public function handle(
        N8nAssistantProvider $provider,
        AssistantSensitiveContentDetector $sensitiveDetector,
        AssistantKnowledgeCatalog $knowledgeCatalog,
    ): int {
        if (app()->environment('production') && ! $this->option('force')) {
            $this->error('En producción debes revisar la prueba y repetir con --force.');

            return self::FAILURE;
        }

        if ((string) config('assistant.n8n.webhook_url', '') === ''
            || (string) config('assistant.n8n.secret', '') === '') {
            $this->error('La conexión de consulta n8n no está configurada.');

            return self::FAILURE;
        }

        $question = trim((string) $this->option('question'));
        $role = strtolower(trim((string) $this->option('role')));
        $module = strtolower(trim((string) $this->option('module')));

        if ($question === '' || mb_strlen($question) > (int) config('assistant.max_question_length', 500)) {
            $this->error('La pregunta sintética no tiene una longitud válida.');

            return self::INVALID;
        }

        if ($sensitiveDetector->containsSensitiveData($question)) {
            $this->error('La prueba fue rechazada porque parece contener información sensible.');

            return self::INVALID;
        }

        $allowedModules = $this->roleModules($role);
        if ($allowedModules === [] || ! in_array($module, $allowedModules, true)) {
            $this->error('El rol o módulo sintético no es válido para MediFlow.');

            return self::INVALID;
        }

        if (! config('assistant.remote_enabled', false)) {
            $this->warn('La asistencia remota del widget continúa deshabilitada; solo se probará el proveedor.');
        }

        $context = new AssistantContext(
            requestId: Str::uuid()->toString(),
            question: $question,
            canonicalRole: $role,
            currentModule: $module,
            currentRoute: null,
            connectionState: 'ONLINE',
            locale: (string) config('assistant.locale', 'es-EC'),
            knowledgeVersion: $knowledgeCatalog->version(),
            timestamp: now('UTC')->toIso8601String(),
            allowedModules: $allowedModules,
        );

        $startedAt = microtime(true);
        $result = $provider->answer($context);
        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

        if ($result->fallbackUsed) {
            $this->warn('Conexión: el proveedor no entregó una respuesta remota; se usó el fallback seguro.');
        } else {
            $this->info('Conexión: solicitud remota completada correctamente.');
        }
        $this->line('Status: '.($result->fallbackUsed ? 'FALLBACK' : 'REMOTE'));
        $this->line('Request ID: '.$result->requestId);
        $this->line('Source: '.$result->source);
        $this->line('Confidence: '.($result->confidence ?? 'n/a'));
        $this->line('Fallback: '.($result->fallbackUsed ? 'sí' : 'no'));
        $this->line('Tiempo: '.$durationMs.' ms');
        $this->line('Respuesta segura: '.$result->answer);

        if ($this->option('show-metadata')) {
            $this->table(
                ['request_id', 'source', 'confidence', 'fallback', 'duration_ms', 'code'],
                [[
                    $result->requestId,
                    $result->source,
                    $result->confidence ?? 'n/a',
                    $result->fallbackUsed ? 'sí' : 'no',
                    $durationMs,
                    $result->code ?? 'none',
                ]],
            );
        }

        if ($this->option('expect-remote') && ($result->source !== 'remote' || $result->fallbackUsed)) {
            $this->error('No se recibió la respuesta remota esperada.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /** @return array<int, string> */
    private function roleModules(string $role): array
    {
        return app(AssistantKnowledgeCatalog::class)->modulesForRole($role);
    }
}
