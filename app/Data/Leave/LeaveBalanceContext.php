<?php

declare(strict_types=1);

namespace App\Data\Leave;

use Spatie\LaravelData\Data;

class LeaveBalanceContext extends Data
{
    /**
     * @param array<int, array{start_date:string,end_date:string|null}> $employee_blocked_periods
     */
    public function __construct(
        public int $employee_id,
        public int $company_id,
        public int $year,
        public int $remaining_minutes,
        public ?string $employment_start_date,
        public string $leave_type,
        public bool $has_employer_exception,
        public array $employee_blocked_periods,
        public bool $agreement_age_bonus_transfer,
    ) {
    }
}
