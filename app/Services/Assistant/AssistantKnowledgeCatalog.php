<?php

namespace App\Services\Assistant;

use JsonException;

final class AssistantKnowledgeCatalog
{
    /** @var array<string, mixed>|null */
    private ?array $document = null;

    /** @return array<int, string> */
    public function modules(): array
    {
        return array_keys((array) ($this->document()['catalogs']['modules'] ?? []));
    }

    /** @return array<int, string> */
    public function modulesForRole(string $role): array
    {
        $modules = $this->document()['catalogs']['role_modules'][$role] ?? [];

        return array_values(array_filter(
            is_array($modules) ? $modules : [],
            fn (mixed $module): bool => is_string($module) && $module !== '',
        ));
    }

    public function version(): int|string|null
    {
        return $this->document()['schema_version'] ?? null;
    }

    /** @return array<string, mixed> */
    private function document(): array
    {
        if ($this->document !== null) {
            return $this->document;
        }

        try {
            $json = file_get_contents(resource_path('assistant/knowledge-base.json'));
            $this->document = is_string($json)
                ? json_decode($json, true, 512, JSON_THROW_ON_ERROR)
                : [];
        } catch (JsonException) {
            $this->document = [];
        }

        return $this->document;
    }
}
