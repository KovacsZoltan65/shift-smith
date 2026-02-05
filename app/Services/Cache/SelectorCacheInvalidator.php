<?php

namespace App\Services\Cache;

final class SelectorCacheInvalidator
{
    public function __construct(
        private readonly CacheVersionService $versions
    ) {}

    public function companies(): int
    {
        return $this->versions->bump('selectors.companies');
    }

    public function employees(): int
    {
        return $this->versions->bump('selectors.employees');
    }

    public function allSelectors(): void
    {
        $this->companies();
        $this->employees();
        // később bővíted ide
    }
}
