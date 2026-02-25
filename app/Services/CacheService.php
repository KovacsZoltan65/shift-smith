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

    private function contextTenantId(): ?int
    {
        try {
            if (! app()->bound('context')) {
                return null;
            }

            $context = app('context');
            if (! \is_object($context) || ! method_exists($context, 'get')) {
                return null;
            }

            $tenantId = $context->get(self::TENANT_CTX_KEY);

            return is_numeric($tenantId) && (int) $tenantId > 0
                ? (int) $tenantId
                : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolveTenantPrefix(): string
    {
        // 1) Spatie Context kulcs
        $contextTenantId = $this->contextTenantId();
        if ($contextTenantId !== null) {
            return "tenant:{$contextTenantId}";
        }

        // 2) Current tenant fallback
        $currentTenantId = TenantGroup::current()?->id;
        if (is_numeric($currentTenantId) && (int) $currentTenantId > 0) {
            return 'tenant:'.(int) $currentTenantId;
        }

        // 3) Landlord context
        return 'landlord';
    }

    private function qualifyTag(string $tag): string
    {
        $prefix = $this->resolveTenantPrefix();
        return "{$prefix}:{$tag}";
    }

    private function qualifyKey(string $qualifiedTag, string $keyInput): string
    {
        return "{$qualifiedTag}:".hash('sha256', $keyInput);
    }

    /**
     * Summary of put
     * @param string $tag
     * @param string $key
     * @param mixed $value
     * @param DateTimeInterface|DateInterval|int $ttl
     * @return void
     */
    public function put(string $tag, string $key, mixed $value, DateTimeInterface|DateInterval|int $ttl = 3600): void
    {
        $qualifiedTag = $this->qualifyTag($tag);
        $cacheKey = $this->qualifyKey($qualifiedTag, $key);

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
        $cacheKey = $this->qualifyKey($qualifiedTag, $key);

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
            $prefixScope = $this->resolveTenantPrefix();

            $qualifiedPattern = str_starts_with($pattern, 'tenant:') || str_starts_with($pattern, 'landlord:')
                ? $pattern
                : "{$prefixScope}:{$pattern}";

            $prefixWithColon = $prefix !== '' ? $prefix.':' : '';
            $keys = $store->connection()->keys($prefixWithColon.$qualifiedPattern);

            foreach ($keys as $redisKey) {
                $redisKey = (string) $redisKey;

                // Cache::forget() prefix nélküli kulcsot vár
                $cacheKey = $prefixWithColon !== '' && str_starts_with($redisKey, $prefixWithColon)
                    ? substr($redisKey, strlen($prefixWithColon))
                    : $redisKey;

                Cache::forget($cacheKey);
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

        if (! \in_array($key, $keys, true)) {
            $keys[] = $key;
            Cache::put("{$tag}_keys", $keys, 3600);
        }
    }
}
