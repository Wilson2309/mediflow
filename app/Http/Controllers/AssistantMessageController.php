<?php

namespace App\Http\Controllers;

use App\Data\AssistantContext;
use App\Data\AssistantResult;
use App\Http\Requests\AssistantMessageRequest;
use App\Services\Assistant\AssistantInputSanitizer;
use App\Services\Assistant\AssistantKnowledgeCatalog;
use App\Services\Assistant\AssistantProviderManager;
use App\Services\Assistant\AssistantSensitiveContentDetector;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Throwable;

final class AssistantMessageController extends Controller
{
    private const CANONICAL_ROLES = [
        'administrador',
        'recepcionista',
        'caja_finanzas',
        'medico',
        'super_admin',
    ];

    public function __invoke(
        AssistantMessageRequest $request,
        AssistantSensitiveContentDetector $sensitiveContentDetector,
        AssistantInputSanitizer $sanitizer,
        AssistantKnowledgeCatalog $knowledgeCatalog,
        AssistantProviderManager $providerManager,
    ): JsonResponse {
        $requestId = Str::uuid()->toString();
        $validated = $request->validated();
        $user = $request->user();
        $canonicalRole = $user?->getRoleNames()
            ->first(fn (string $role): bool => in_array($role, self::CANONICAL_ROLES, true));

        if (! $canonicalRole) {
            return response()->json(
                AssistantResult::fallback($requestId, 'ROLE_UNAVAILABLE')->toResponseArray(),
            );
        }

        // Se resuelve para aplicar el mismo alcance interno que el resto de MediFlow.
        // Este valor nunca forma parte de AssistantContext ni del payload remoto.
        $activeClinicId = $canonicalRole === 'super_admin' ? null : $user?->activeClinicId();
        if ($canonicalRole !== 'super_admin' && ! $activeClinicId) {
            return response()->json(
                AssistantResult::fallback($requestId, 'CLINIC_UNAVAILABLE')->toResponseArray(),
            );
        }

        if ($sensitiveContentDetector->containsSensitiveData($validated['question'])) {
            return response()->json(AssistantResult::sensitive($requestId)->toResponseArray(), 422);
        }

        if (! config('assistant.remote_enabled', false)) {
            return response()->json(
                AssistantResult::fallback($requestId, 'REMOTE_DISABLED')->toResponseArray(),
            );
        }

        $context = new AssistantContext(
            requestId: $requestId,
            question: $sanitizer->question($validated['question']),
            canonicalRole: $canonicalRole,
            currentModule: $validated['current_module'] ?? 'support',
            currentRoute: $sanitizer->route($validated['current_route'] ?? null),
            connectionState: $validated['connection_state'] ?? 'ONLINE',
            locale: (string) config('assistant.locale', 'es-EC'),
            knowledgeVersion: $validated['knowledge_version'] ?? $knowledgeCatalog->version(),
            timestamp: now('UTC')->toIso8601String(),
            allowedModules: $knowledgeCatalog->modulesForRole($canonicalRole),
        );

        try {
            $result = $providerManager->resolve()->answer($context);
        } catch (Throwable) {
            $result = AssistantResult::fallback($requestId, 'PROVIDER_UNAVAILABLE');
        }

        return response()->json($result->toResponseArray());
    }
}
