<?php

namespace app\Services\Cache;

/**
 * Cache kulcs generáló szolgáltatás
 * 
 * Stabil hash generálást biztosít cache kulcsokhoz paraméterek alapján.
 * A paraméterek normalizálása garantálja, hogy azonos adatok mindig
 * ugyanazt a hash-t eredményezzék.
 */
final class CacheKeyService
{
    /**
     * Stabil hash generálás paraméterekből
     * 
     * Normalizálja a paramétereket (rendezés, típus konverzió) és
     * SHA-256 hash-t generál belőlük. Azonos paraméterek mindig
     * ugyanazt a hash-t eredményezik.
     * 
     * @param array<string, scalar|null> $params Paraméterek tömbje
     * @return string SHA-256 hash (64 karakter)
     */
    public static function stableHash(array $params): string
    {
        self::normalize($params);
        return hash('sha256', json_encode($params, JSON_THROW_ON_ERROR));
    }

    /**
     * Paraméterek normalizálása
     * 
     * - Kulcsok ABC sorrendbe rendezése
     * - String '1'/'0' értékek konvertálása boolean-ra
     * 
     * @param array<string, scalar|null> $params Paraméterek tömbje (referencia)
     * @return void
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