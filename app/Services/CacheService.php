<?php

declare(strict_types=1);

namespace App\Services;

use Closure;
use DateInterval;
use DateTimeInterface;
use Illuminate\Cache\RedisStore;
use Illuminate\Cache\TaggableStore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\PermissionRegistrar as SpatiePermissionRegistrar;

class CacheService
{
    public function put(string $tag, string $key, mixed $value, DateTimeInterface|DateInterval|int $ttl = 3600): void
    {
        $cacheKey = "{$tag}_{$key}";

        if (Cache::supportsTags()) {
            Cache::tags([$tag])->put($cacheKey, $value, $ttl);
        } else {
            Cache::put($cacheKey, $value, $ttl);
            $this->storeKey($tag, $cacheKey);
        }
    }
    
    /**
     * @template TCacheValue
     *
     * @param Closure():TCacheValue $callback
     * @return TCacheValue
     */
    public function remember(string $tag, string $key, Closure $callback, DateTimeInterface|DateInterval|int $ttl = 3600): mixed
    {
        $cacheKey = "{$tag}:{$key}";

        if (Cache::supportsTags()) {
            /** @var TCacheValue $value */
            $value = Cache::tags([$tag])->remember($cacheKey, $ttl, $callback);

            return $value;
        }

        /** @var TCacheValue $value */
        $value = Cache::remember($cacheKey, $ttl, $callback);

        return $value;
    }
    
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
        if (\in_array($tag, ['roles', 'permissions', \App\Models\Role::getTag()], true)) {
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

        if (method_exists($store, 'getKeys')) {
            /** @var array<int,string> $keys */
            $keys = $store->getKeys($pattern);
            foreach ($keys as $key) {
                Cache::forget($key);
            }

            return;
        }

        Log::warning('Cache driver does not support pattern-based deletion.');
    }
    
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