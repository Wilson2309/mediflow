<?php

namespace App\Services\Assistant;

use JsonException;
use RuntimeException;
use Symfony\Component\Process\Process;

final class AssistantN8nDocumentPackage
{
    private const CANONICAL_ROLES = [
        'administrador',
        'recepcionista',
        'caja_finanzas',
        'medico',
        'super_admin',
    ];

    public function path(): string
    {
        return base_path('n8n/knowledge/assistant-documents.json');
    }

    /** @return array<string, mixed> */
    public function load(bool $regenerate = false): array
    {
        if ($regenerate || ! is_file($this->path())) {
            $this->generate();
        }

        try {
            return $this->readValidatedPackage();
        } catch (RuntimeException $exception) {
            if ($regenerate) {
                throw $exception;
            }

            // La fuente autoritativa pudo cambiar después del último export.
            // Se regenera una sola vez y se vuelve a validar desde cero.
            $this->generate();

            return $this->readValidatedPackage();
        }
    }

    /** @param array<string, mixed> $package */
    public function assertValid(array $package): void
    {
        $this->validate($package);
    }

    /** @return array<string, mixed> */
    private function readValidatedPackage(): array
    {
        $raw = file_get_contents($this->path());
        if ($raw === false) {
            throw new RuntimeException('No se pudo leer el paquete documental del asistente.');
        }

        try {
            $package = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('El paquete documental no contiene JSON válido.', previous: $exception);
        }

        if (! is_array($package)) {
            throw new RuntimeException('El paquete documental no tiene el esquema esperado.');
        }

        $this->validate($package);

        return $package;
    }

    private function generate(): void
    {
        $process = new Process(['node', 'scripts/build-n8n-assistant-documents.mjs'], base_path());
        $process->setTimeout(60);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('No se pudo generar el paquete documental del asistente.');
        }
    }

    /** @param array<string, mixed> $package */
    private function validate(array $package): void
    {
        $required = [
            'schema_version', 'source', 'locale', 'knowledge_schema_version',
            'knowledge_version', 'document_count', 'checksum_algorithm', 'checksum', 'documents',
        ];

        if (array_keys($package) !== $required) {
            throw new RuntimeException('La cabecera del paquete documental no tiene el esquema exacto.');
        }

        $knowledge = $this->knowledgeBase();
        $expectedDocuments = $this->expectedDocuments($knowledge);
        $expectedIds = array_keys($expectedDocuments);

        if ($package['schema_version'] !== 1
            || $package['source'] !== ($knowledge['source'] ?? 'resources/assistant/knowledge-base.json')
            || $package['locale'] !== ($knowledge['defaults']['locale'] ?? 'es-EC')
            || $package['knowledge_schema_version'] !== $knowledge['schema_version']
            || $package['knowledge_version'] !== $knowledge['schema_version']
            || $package['checksum_algorithm'] !== 'sha256'
            || ! is_int($package['document_count'])
            || $package['document_count'] < 1
            || $package['document_count'] !== count($expectedDocuments)
            || ! is_array($package['documents'])
            || count($package['documents']) !== $package['document_count']
            || ! is_string($package['checksum'])
            || preg_match('/^[a-f0-9]{64}$/', $package['checksum']) !== 1) {
            throw new RuntimeException('La cabecera del paquete documental es inválida o está desactualizada.');
        }

        $actualIds = [];
        foreach ($package['documents'] as $document) {
            if (! is_array($document)
                || array_keys($document) !== ['document_id', 'content', 'metadata']
                || ! is_string($document['document_id'])
                || ! array_key_exists($document['document_id'], $expectedDocuments)
                || ! is_string($document['content'])
                || $document['content'] === ''
                || mb_strlen($document['content']) > 12000
                || ! is_array($document['metadata'])) {
                throw new RuntimeException('El paquete contiene un documento inválido, inesperado o duplicado.');
            }

            $actualIds[] = $document['document_id'];
            if ($document !== $expectedDocuments[$document['document_id']]) {
                throw new RuntimeException('Un documento no coincide exactamente con su entrada y rol autoritativos.');
            }
        }

        if ($actualIds !== $expectedIds || count(array_unique($actualIds)) !== count($actualIds)) {
            throw new RuntimeException('El conjunto u orden documental no coincide con la fuente autoritativa.');
        }

        $checksumPayload = [
            'knowledge_schema_version' => $package['knowledge_schema_version'],
            'documents' => $package['documents'],
        ];
        $encoded = json_encode(
            $checksumPayload,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );

        if (! hash_equals($package['checksum'], hash('sha256', $encoded))) {
            throw new RuntimeException('El checksum del paquete documental no coincide.');
        }
    }

    /**
     * @param  array<string, mixed>  $knowledge
     * @return array<string, array<string, mixed>>
     */
    private function expectedDocuments(array $knowledge): array
    {
        $defaults = $knowledge['defaults'] ?? [];
        $moduleCatalog = $knowledge['catalogs']['modules'] ?? [];
        $forbiddenPhrases = array_map(
            static fn (mixed $value): string => mb_strtolower(trim((string) $value), 'UTF-8'),
            $knowledge['catalogs']['forbidden_phrases'] ?? [],
        );
        $documents = [];

        foreach ($knowledge['entries'] ?? [] as $rawEntry) {
            if (! is_array($rawEntry)) {
                throw new RuntimeException('La base de conocimiento contiene una entrada inválida.');
            }

            $entry = array_replace($defaults, $rawEntry);
            $entry['escalation'] = array_replace(
                is_array($defaults['escalation'] ?? null) ? $defaults['escalation'] : [],
                is_array($rawEntry['escalation'] ?? null) ? $rawEntry['escalation'] : [],
            );
            $roles = $this->normalizedArray($entry['roles'] ?? []);
            $modules = $this->normalizedArray($entry['modules'] ?? []);
            $version = is_int($entry['version'] ?? null)
                ? $entry['version']
                : (int) ($defaults['version'] ?? 1);
            $content = $this->documentContent($entry);
            $normalizedContent = mb_strtolower($content, 'UTF-8');

            foreach ($forbiddenPhrases as $phrase) {
                if ($phrase !== '' && str_contains($normalizedContent, $phrase)) {
                    throw new RuntimeException('La fuente contiene una función no soportada.');
                }
            }

            if (preg_match('~(?:^|[\s(])(?:app|bootstrap|config|database|resources|routes|storage|vendor)[/\\\\]|(?:^|\s)\.env\b|\b(?:artisan|composer\.json|package\.json)\b|\.php\b~iu', $content) === 1) {
                throw new RuntimeException('La fuente contiene evidencia tecnica interna no autorizada.');
            }

            foreach ($roles as $role) {
                if (! in_array($role, self::CANONICAL_ROLES, true)) {
                    throw new RuntimeException('La fuente contiene un rol no canónico.');
                }

                $roleModules = array_values(array_filter(
                    $modules,
                    fn (string $module): bool => in_array(
                        $role,
                        $moduleCatalog[$module]['roles'] ?? [],
                        true,
                    ),
                ));
                if ($roleModules === []) {
                    throw new RuntimeException('Una entrada no tiene módulos autorizados para uno de sus roles.');
                }

                $documentId = $entry['id'].':'.$role.':v'.$version;
                if (isset($documents[$documentId])) {
                    throw new RuntimeException('La fuente genera un identificador documental duplicado.');
                }

                $documents[$documentId] = [
                    'document_id' => $documentId,
                    'content' => $content,
                    'metadata' => [
                        'entry_id' => $entry['id'],
                        'role' => $role,
                        'modules' => $roleModules,
                        'locale' => $entry['locale'],
                        'status' => $entry['status'],
                        'knowledge_version' => $version,
                        'requires_online' => (bool) $entry['requires_online'],
                        'source' => 'knowledge-base.json',
                    ],
                ];
            }
        }

        ksort($documents, SORT_STRING);

        return $documents;
    }

    /** @param array<string, mixed> $entry */
    private function documentContent(array $entry): string
    {
        $escalation = ($entry['escalation']['allowed'] ?? false) && ($entry['escalation']['message'] ?? '') !== ''
            ? ['Permitido: sí. '.$entry['escalation']['message']]
            : ['Permitido: no.'];

        return implode("\n\n", array_filter([
            'Título: '.$entry['title'],
            'Pregunta: '.$entry['question'],
            'Respuesta: '.$entry['answer'],
            $this->section('Pasos', $this->normalizedArray($entry['steps'] ?? [])),
            $this->section('Restricciones en línea', $this->normalizedArray($entry['online_restrictions'] ?? [])),
            $this->section('Formas alternativas de preguntar', $this->normalizedArray($entry['aliases'] ?? [])),
            $this->section('Escalado', $escalation),
        ], static fn (string $value): bool => $value !== ''));
    }

    /** @param array<int, string> $values */
    private function section(string $label, array $values): string
    {
        return $values === [] ? '' : $label.":\n- ".implode("\n- ", $values);
    }

    /** @return array<int, string> */
    private function normalizedArray(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn (mixed $item): string => trim((string) $item), $value),
            static fn (string $item): bool => $item !== '',
        ));
    }

    /** @return array<string, mixed> */
    private function knowledgeBase(): array
    {
        $raw = file_get_contents(resource_path('assistant/knowledge-base.json'));
        if ($raw === false) {
            throw new RuntimeException('No se pudo leer la base de conocimiento.');
        }

        try {
            $knowledge = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('La base de conocimiento no contiene JSON válido.', previous: $exception);
        }

        if (! is_array($knowledge)
            || ! is_int($knowledge['schema_version'] ?? null)
            || ! is_array($knowledge['defaults'] ?? null)
            || ! is_array($knowledge['entries'] ?? null)) {
            throw new RuntimeException('La base de conocimiento no tiene el esquema esperado.');
        }

        return $knowledge;
    }
}
