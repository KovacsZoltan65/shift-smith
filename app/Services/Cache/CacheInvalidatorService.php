<?php

namespace App\Services\Cache;

use Illuminate\Support\Facades\Cache;

final class CacheInvalidatorService
{
    private const KEY_COMPANIES_SELECT_VERSION = 'v:companies_select';

    public function companiesSelectVersion(): int
    {
        return (int) Cache::get(self::KEY_COMPANIES_SELECT_VERSION, 1);
    }

    public function bumpCompaniesSelect(): int
    {
        // database cache store-on is működik
        if (! Cache::has(self::KEY_COMPANIES_SELECT_VERSION)) {
            Cache::forever(self::KEY_COMPANIES_SELECT_VERSION, 1);
        }

        return (int) Cache::increment(self::KEY_COMPANIES_SELECT_VERSION);
    }
}
