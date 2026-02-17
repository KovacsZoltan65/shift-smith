<?php

declare(strict_types=1);

namespace App\Services;

use App\Traits\Functions;
use Closure;
use DateInterval;
use DateTimeInterface;
use Illuminate\Cache\RedisStore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Cache szolgáltatás osztály
 * 
 * Egységes cache kezelést biztosít tag-alapú és tag nélküli cache store-okhoz.
 * Támogatja a Redis, Memcached és file-based cache driver-eket.
 */
class CacheService
{
    use Functions;
    
    /**
     * Érték tárolása a cache-ben
     * 
     * Tag-alapú cache esetén (Redis, Memcached) natív tag támogatást használ.
     * Egyéb driver-ek esetén manuális kulcs nyilvántartást végez.
     * 
     * @param string $tag Cache tag azonosító (pl. 'users', 'companies')
     * @param string $key Cache kulcs azonosító
     * @param mixed $value Tárolandó érték
     * @param DateTimeInterface|DateInterval|int $ttl Élettartam másodpercben vagy DateTime objektum
     * @return void
     */
    public function put(string $tag, string $key, mixed $value, DateTimeInterface|DateInterval|int $ttl = 3600): void
    {
        //$cacheKey = "{$tag}:{$key}";
        $cacheKey = $this->generateCacheKey($tag, $key);

        if (Cache::supportsTags()) {
            Cache::tags([$tag])->put($cacheKey, $value, $ttl);
        } else {
            Cache::put($cacheKey, $value, $ttl);
            $this->storeKey($tag, $cacheKey);
        }
    }
    
    /**
     * Érték lekérése cache-ből vagy callback végrehajtása
     * 
     * Ha az érték nincs cache-ben, végrehajtja a callback-et és eltárolja az eredményt.
     * Generic típus támogatással biztosítja a típusbiztonságot.
     * 
     * @template TCacheValue
     * 
     * @param string $tag Cache tag azonosító
     * @param string $key Cache kulcs azonosító
     * @param Closure():TCacheValue $callback Callback függvény, ami előállítja az értéket
     * @param DateTimeInterface|DateInterval|int $ttl Élettartam másodpercben vagy DateTime objektum
     * @return TCacheValue A cache-ből vagy callback-ből származó érték
     */
    public function remember(
        string $tag,
        string $key,
        Closure $callback,
        DateTimeInterface|DateInterval|int $ttl = 3600
    ): mixed {
        $cacheKey = $this->generateCacheKey($tag, $key);

        if (Cache::supportsTags()) {
            /** @var TCacheValue $value */
            $value = Cache::tags([$tag])->remember($cacheKey, $ttl, $callback);

            return $value;
        }

        /** @var TCacheValue $value */
        $value = Cache::remember($cacheKey, $ttl, $callback);

        return $value;
    }
    
    /**
     * Összes cache bejegyzés törlése egy tag alapján
     * 
     * Tag-alapú cache esetén natív flush-t használ.
     * Egyéb driver-ek esetén a manuálisan nyilvántartott kulcsokat törli.
     * 
     * @param string $tag Cache tag azonosító
     * @return void
     */
    public function forgetAll(string $tag): void
    {
        if (Cache::supportsTags()) {
            Cache::tags([$tag])->flush();

            return;
        }

        /** @var array<int,string> $keys */
        $keys = Cache::get("{$tag}_keys", []);
        foreach ($keys as $key) {
            Cache::forget($key);
        }
        Cache::forget("{$tag}_keys");
    }
    
    /**
     * Biztonságos tag-flush:
     * - Ha a store támogatja a tageket (pl. redis/memcached), flusholja a megadott taget.
     * - Ha nem, akkor NO-OP helyett célzott fallback (Spatie permission cache flush),
     *   és csak DEBUG módban logoljuk, hogy nincs tag támogatás.
     */
    /*
    public function forgetByTag(string $tag): void
    {
        $store = Cache::getStore();

        if ($store instanceof TaggableStore) {
            Cache::tags([$tag])->flush();

            return;
        }

        // Fallback – a szerep/jogosultság területén legalább a Spatie cache ürüljön
        if (\in_array($tag, ['roles', 'permissions', \App\Models\Admin\Role::getTag()], true)) {
            app(SpatiePermissionRegistrar::class)->forgetCachedPermissions();
        }

        // Csak debug módban írjunk logot, és csak egyszer a kérés élettartama alatt
        if (config('app.debug')) {
            static $warned = false;
            if (! $warned) {
                logger()->debug('Cache tag flush skipped: store has no tag support', [
                    'store' => \get_class($store),
                    'tag'   => $tag,
                ]);
                $warned = true;
            }
        }
    }
    */
    
    /**
     * Minta szerinti törlés, ha a store tudja; különben kulturált no-op.
     * (Pl. saját Redis store implementációban lehet "deleteUsingPattern" metódus.)
     */
    /*
    public function forgetByPattern(string $pattern): void
    {
        $store = Cache::getStore();

        if (\method_exists($store, 'deleteUsingPattern')) {
            $store->deleteUsingPattern($pattern);

            return;
        }

        if (config('app.debug') && env('CACHE_TAG_DEBUG', false)) {
            static $warned = false;
            if (! $warned) {
                logger()->debug('Pattern-based cache deletion is not supported by this store', [
                    'store'   => \get_class($store),
                    'pattern' => $pattern,
                ]);
                $warned = true;
            }
        }
    }
    */
    
    /**
     * Cache bejegyzések törlése minta alapján
     * 
     * Csak Redis cache driver esetén működik, pattern-based kulcs kereséssel.
     * Más driver-ek esetén figyelmeztetést logol.
     * 
     * @param string $pattern Keresési minta (pl. 'users:*', 'companies:active:*')
     * @return void
     */
    public function forgetAllMatching(string $pattern): void
    {
        $store = Cache::getStore();

        if ($store instanceof RedisStore) {
            $prefix = (string) config('cache.prefix');
            $keys   = $store->connection()->keys($prefix.":{$pattern}");
            foreach ($keys as $key) {
                Cache::forget(str_replace($prefix.':', '', (string) $key));
            }

            return;
        }

        // Ha a store nem Redis és nincs pattern-based törlés támogatás
        Log::warning('Cache driver does not support pattern-based deletion.', [
            'store' => get_class($store),
            'pattern' => $pattern,
        ]);
    }
    
    /**
     * Cache kulcs nyilvántartásba vétele tag nélküli driver-ek esetén
     * 
     * Manuálisan tárolja a kulcsokat egy listában, hogy később törölhetők legyenek.
     * Csak akkor hívódik, ha a cache driver nem támogatja a tag-eket.
     * 
     * @param string $tag Cache tag azonosító
     * @param string $key Cache kulcs azonosító
     * @return void
     */
    protected function storeKey(string $tag, string $key): void
    {
        /** @var array<int,string> $keys */
        $keys = Cache::get("{$tag}_keys", []);
        if (! in_array($key, $keys, true)) {
            $keys[] = $key;
            Cache::put("{$tag}_keys", $keys, 3600);
        }
    }
}