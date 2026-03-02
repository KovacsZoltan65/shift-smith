<?php

declare(strict_types=1);

namespace App\Data\Leave;

use Spatie\LaravelData\Data;

class AnnualLeaveEntitlementResult extends Data
{
    /**
     * @param array<string, int> $breakdown
     */
    public function __construct(
        public int $employee_id,
        public int $company_id,
        public int $year,
        public int $base_minutes,
        public int $age_bonus_minutes,
        public int $child_bonus_minutes,
        public int $disability_bonus_minutes,
        public int $youth_bonus_minutes,
        public int $total_minutes,
        public array $breakdown = [],
    ) {
    }
}
