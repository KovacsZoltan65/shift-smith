<?php

namespace App\Services\Cache;

final class CacheInvalidatorService
{
    private const NS_SELECTORS_COMPANIES = 'selectors.companies';

    public function __construct(
        private readonly CacheVersionService $versions
    ) {
    }

    public function companiesSelectVersion(): int
    {
        return $this->versions->get(self::NS_SELECTORS_COMPANIES);
    }

    public function bumpCompaniesSelect(): int
    {
        return $this->versions->bump(self::NS_SELECTORS_COMPANIES);
    }
}
