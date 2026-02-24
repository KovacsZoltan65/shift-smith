<?php

declare(strict_types=1);

namespace App\Data\Settings;

use Spatie\LaravelData\Data;

class SettingSaveValueData extends Data
{
    public function __construct(
        public string $key,
        public mixed $value,
    ) {}
}

