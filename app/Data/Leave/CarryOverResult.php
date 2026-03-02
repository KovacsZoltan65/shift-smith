<?php

declare(strict_types=1);

namespace App\Data\Leave;

use Spatie\LaravelData\Data;

class CarryOverResult extends Data
{
    public function __construct(
        public int $transferable_minutes,
        public int $must_expire_minutes,
        public ?string $valid_until,
        public string $rule_applied,
    ) {
    }
}
