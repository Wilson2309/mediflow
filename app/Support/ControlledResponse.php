<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ControlledResponse
{
    private const ERROR_MESSAGE = 'No se pudo completar la operación.';

    private const SUCCESS_MESSAGE = 'Operación completada correctamente.';

    public static function error(
        Request $request,
        int $status,
        string $code,
    ): Response {
        if ($request->expectsJson()) {
            return self::jsonError($status, $code);
        }

        abort($status);
    }

    public static function jsonError(
        int $status,
        string $code,
        array $extra = [],
    ): JsonResponse {
        return response()->json([
            'message' => self::ERROR_MESSAGE,
            'code' => $code,
            ...$extra,
        ], $status);
    }

    public static function jsonSuccess(string $code): JsonResponse
    {
        return response()->json([
            'message' => self::SUCCESS_MESSAGE,
            'code' => $code,
        ]);
    }
}
