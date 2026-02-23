<?php

declare(strict_types=1);

namespace App\Data\Scheduling\AutoPlan;

use Spatie\LaravelData\Data;

class DemandItemData extends Data
{
    public function __construct(
        public int $shift_id,
        public int $required_count,
    ) {}
}
