<?php

declare(strict_types=1);

namespace App\Interfaces;

use App\Data\Employee\EmployeeLeaveProfileDTO;

interface EmployeeProfileRepositoryInterface
{
    public function findByEmployeeInCompany(int $companyId, int $employeeId): ?EmployeeLeaveProfileDTO;

    /**
     * @param array{
     *   children_count:int,
     *   disabled_children_count:int,
     *   is_disabled:bool
     * } $attributes
     */
    public function upsertForEmployeeInCompany(int $companyId, int $employeeId, array $attributes): EmployeeLeaveProfileDTO;
}
