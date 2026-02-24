<?php

declare(strict_types=1);

namespace App\Data\Settings;

use Spatie\LaravelData\Data;

class SettingGroupData extends Data
{
    /**
     * @param array<int, SettingItemData> $items
     */
    public function __construct(
        public string $group,
        public array $items,
    ) {}
}

