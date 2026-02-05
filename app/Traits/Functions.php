<?php

namespace App\Traits;

use App\Http\Controllers\ActivityController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\File;
use Throwable;

trait Functions
{
    public function handleException(Throwable $th, string $defaultMessage, int $statusCode): JsonResponse
    {
        return response()->json([
            'success' => APP_FALSE,
            'error'   => $defaultMessage,
            'details' => $th->getMessage(),
        ], $statusCode);
    }

    /**
     * Validációs szabályok betöltése JSON-ból.
     *
     * @return array<string, mixed>
     */
    public function getValidationRules(): array
    {
        $path = resource_path('js/Validation/ValidationRules.json');

        if (! is_file($path) || ! is_readable($path)) {
            // ide rakhatsz logolást is, ha akarod
            return [];
        }

        try {
            $json = File::get($path); // mindig string; hiba esetén kivétel
            /** @var array<string, mixed> $rules */
            $rules = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

            return $rules;
        } catch (Throwable $e) {
            // opcionális: Log::warning('Validation rules load failed', ['e' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Egységes hibalog.
     *
     * @param array<string, mixed> $params
     */
    /*
    public function logError(Throwable $ex, string $context, array $params): void
    {
        ActivityController::logServerError($ex, [
            'context'  => $context,
            'params'   => $params,
            'route'    => request()->path(),
            'type'     => get_class($ex),
            'severity' => 'error',
        ]);
    }
    */

    /**
     * @param string $tag
     * @param string $key
     * @return string
     */
    public function generateCacheKey(string $tag, string $key): string
    {
        return "{$tag}:" . hash('sha256', $key);
    }
}
