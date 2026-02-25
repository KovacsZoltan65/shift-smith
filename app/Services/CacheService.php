<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\TenantGroup;
use App\Traits\Functions;
use Closure;
use DateInterval;
use DateTimeInterface;
use Illuminate\Cache\RedisStore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CacheService
{
    use Functions;

    private const TENANT_CTX_KEY = 'tenantId';

    private function tenantId(): ?int
    {
        // Spatie Multitenancy context
        $tenantId = app()->bound('context') ? app('context')->get(self::TENANT_CTX_KEY) : null;

        if (! is_numeric($tenantId)) {
            $tenantId = TenantGroup::current()?->id;
        }

        if (! is_numeric($tenantId)) {
            return null;
        }

        $id = (int) $tenantId;

        return $id > 0 ? $id : null;
    }

    private function qualifyTag(string $tag): string
    {
        $tenantId = $this->tenantId();

        // Ha nincs tenant, landlord/guest mód: elkülönített namespace
        if ($tenantId === null) {
            return "landlord:{$tag}";
        }

        return "tenant:{$tenantId}:{$tag}";
    }

    private function qualifyKey(string $tag, string $key): string
    {
        // kulcs tenant- + tag-aware
        // pl: tenant:76:companies:list?page=1
        return $this->qualifyTag($tag).":{$key}";
    }

    public function put(string $tag, string $key, mixed $value, DateTimeInterface|DateInterval|int $ttl = 3600): void
    {
        $qualifiedTag = $this->qualifyTag($tag);
        $cacheKey = $this->qualifyKey($tag, $key);

        if (Cache::supportsTags()) {
            Cache::tags([$qualifiedTag])->put($cacheKey, $value, $ttl);
            return;
        }

        Cache::put($cacheKey, $value, $ttl);
        $this->storeKey($qualifiedTag, $cacheKey);
    }

    public function remember(
        string $tag,
        string $key,
        Closure $callback,
        DateTimeInterface|DateInterval|int $ttl = 3600
    ): mixed {
        $qualifiedTag = $this->qualifyTag($tag);
        $cacheKey = $this->qualifyKey($tag, $key);

        if (Cache::supportsTags()) {
            return Cache::tags([$qualifiedTag])->remember($cacheKey, $ttl, $callback);
        }

        return Cache::remember($cacheKey, $ttl, $callback);
    }

    public function forgetAll(string $tag): void
    {
        $qualifiedTag = $this->qualifyTag($tag);

        if (Cache::supportsTags()) {
            Cache::tags([$qualifiedTag])->flush();
            return;
        }

        /** @var array<int,string> $keys */
        $keys = Cache::get("{$qualifiedTag}_keys", []);
        foreach ($keys as $key) {
            Cache::forget($key);
        }
        Cache::forget("{$qualifiedTag}_keys");
    }

    public function forgetAllMatching(string $pattern): void
    {
        $store = Cache::getStore();

        if ($store instanceof RedisStore) {
            $prefix = (string) config('cache.prefix');

            // pattern legyen tenant-aware a hívó oldalon:
            // pl: tenant:76:companies:*
            $keys = $store->connection()->keys($prefix.":{$pattern}");

            foreach ($keys as $key) {
                Cache::forget(str_replace($prefix.':', '', (string) $key));
            }

            return;
        }

        Log::warning('Cache driver does not support pattern-based deletion.', [
            'store' => get_class($store),
            'pattern' => $pattern,
        ]);
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
