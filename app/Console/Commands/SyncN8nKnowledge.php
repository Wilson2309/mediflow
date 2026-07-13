<?php

namespace App\Console\Commands;

use App\Services\Assistant\AssistantN8nDocumentPackage;
use App\Services\Assistant\N8nKnowledgeSyncService;
use Illuminate\Console\Command;
use RuntimeException;

final class SyncN8nKnowledge extends Command
{
    protected $signature = 'assistant:sync-n8n-knowledge
        {--dry-run : Valida y resume el paquete sin realizar solicitudes}
        {--batch=10 : Cantidad de documentos por lote, entre 1 y 100}
        {--force : Regenera y autoriza un reenvío completo explícito}
        {--provider=supabase : Destino n8n: supabase o simple}';

    protected $description = 'Sincroniza el conocimiento no sensible de MediFlow con el workflow seguro de n8n';

    public function handle(
        AssistantN8nDocumentPackage $documents,
        N8nKnowledgeSyncService $syncService,
    ): int {
        $provider = strtolower(trim((string) $this->option('provider')));
        $batchSize = filter_var($this->option('batch'), FILTER_VALIDATE_INT);

        if (! in_array($provider, ['supabase', 'simple'], true)) {
            $this->error('Proveedor inválido. Usa supabase o simple.');

            return self::INVALID;
        }

        if (! is_int($batchSize) || $batchSize < 1 || $batchSize > 100) {
            $this->error('El lote debe contener entre 1 y 100 documentos.');

            return self::INVALID;
        }

        if (app()->environment('production')
            && ! $this->option('dry-run')
            && ! $this->option('force')) {
            $this->error('En producción debes revisar el paquete y repetir con --force.');

            return self::FAILURE;
        }

        try {
            $package = $documents->load((bool) $this->option('force'));
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Proveedor', 'Documentos', 'Lotes', 'Versión', 'Checksum'],
            [[
                $provider,
                $package['document_count'],
                (int) ceil($package['document_count'] / $batchSize),
                $package['knowledge_version'],
                $package['checksum'],
            ]],
        );

        if ($this->option('dry-run')) {
            $this->info('Dry-run completado. No se realizó ninguna solicitud externa.');

            return self::SUCCESS;
        }

        $result = $syncService->sync(
            $package,
            $provider,
            $batchSize,
            function (int $current, int $total, int $accepted): void {
                $this->line("Lote {$current}/{$total}: {$accepted} documentos aceptados.");
            },
        );

        if (! $result['success']) {
            $this->error('La sincronización no se completó. Código: '.$result['error_code']);
            $this->line('Aceptados antes del fallo: '.(int) $result['accepted']);
            $this->line('Rechazados en el lote fallido: '.(int) $result['rejected']);

            return self::FAILURE;
        }

        $this->info('Sincronización completada y manifiesto confirmado.');
        $this->table(
            ['Enviados', 'Aceptados', 'Rechazados', 'Lotes', 'Versión', 'Checksum'],
            [[
                $result['documents_sent'],
                $result['accepted'],
                $result['rejected'],
                $result['batches'],
                $result['knowledge_version'],
                $result['checksum'],
            ]],
        );

        return self::SUCCESS;
    }
}
