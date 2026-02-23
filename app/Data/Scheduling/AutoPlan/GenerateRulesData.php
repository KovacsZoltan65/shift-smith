<?php

declare(strict_types=1);

namespace App\Data\Scheduling\AutoPlan;

use Spatie\LaravelData\Data;

class GenerateRulesData extends Data
{
    public function __construct(
        public ?int $min_rest_hours,
        public ?int $max_consecutive_days,
        public ?bool $weekend_fairness,
    ) {}
}
