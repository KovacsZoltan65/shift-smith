<?php

declare(strict_types=1);

namespace App\Data\Employee;

use Spatie\LaravelData\Data;

final class EmployeeLeaveEntitlementData extends Data
{
    public function __construct(
        public int $employee_id,
        public int $company_id,
        public ?string $birth_date,
    ) {
    }
}
