<?php

namespace app\Services\Cache;

final class CacheKeyService
{
    /**
     * @param array<string, scalar|null> $params
     */
    public static function stableHash(array $params): string
    {
        self::normalize($params);
        return hash('sha256', json_encode($params, JSON_THROW_ON_ERROR));
    }

    /**
     * @param array<string, scalar|null> $params
     */
    private static function normalize(array &$params): void
    {
        // kulcs sorrend fix
        ksort($params);

        // boolok normalizálása (opcionális, de hasznos)
        foreach ($params as $k => $v) {
            if ($v === '1') $params[$k] = true;
            if ($v === '0') $params[$k] = false;
        }
    }
}