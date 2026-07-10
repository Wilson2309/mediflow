<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Throwable;

class HealthCheckController extends Controller
{
    public function app(): JsonResponse
    {
        return $this->noStoreResponse([
            'ok' => true,
            'timestamp' => $this->timestamp(),
            'app' => 'MediFlow',
        ]);
    }

    public function internet(): JsonResponse
    {
        $internetReachable = false;
        $timeout = max(1, min((float) config('services.internet_health.timeout', 2.5), 5));
        $urls = config('services.internet_health.urls', []);

        foreach (is_array($urls) ? $urls : [] as $url) {
            if (! is_string($url) || ! filter_var($url, FILTER_VALIDATE_URL)) {
                continue;
            }

            try {
                $response = Http::acceptJson()
                    ->connectTimeout($timeout)
                    ->timeout($timeout)
                    ->get($url);

                if ($response->successful()) {
                    $internetReachable = true;
                    break;
                }
            } catch (Throwable) {
                // El endpoint solo informa disponibilidad y nunca expone el error externo.
            }
        }

        return $this->noStoreResponse([
            'ok' => true,
            'internet' => $internetReachable,
            'timestamp' => $this->timestamp(),
        ]);
    }

    private function timestamp(): string
    {
        return now(config('app.timezone', 'America/Guayaquil'))->toIso8601String();
    }

    private function noStoreResponse(array $payload): JsonResponse
    {
        return response()->json($payload)->withHeaders([
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }
}
