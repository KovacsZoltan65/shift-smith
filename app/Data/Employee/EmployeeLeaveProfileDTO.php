<?php

declare(strict_types=1);

namespace App\Data\Employee;

use Spatie\LaravelData\Data;

class EmployeeLeaveProfileDTO extends Data
{
    public function __construct(
        public int $employee_id,
        public int $company_id,
        public int $children_count,
        public int $disabled_children_count,
        public bool $is_disabled,
    ) {
    }
}
