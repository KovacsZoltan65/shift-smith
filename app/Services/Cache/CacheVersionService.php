<?php

namespace App\Services\Cache;

use Illuminate\Support\Facades\Cache;

final class CacheVersionService
{
    private const PREFIX = 'v:'; // v:<namespace>

    public function get(string $namespace, int $default = 1): int
    {
        $key = self::PREFIX.$namespace;

        return (int) Cache::get($key, $default);
    }

    public function bump(string $namespace): int
    {
        $key = self::PREFIX.$namespace;

        if (! Cache::has($key)) {
            Cache::forever($key, 1);
        }

        return (int) Cache::increment($key);
    }
}
