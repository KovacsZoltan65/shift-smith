<?php

declare(strict_types=1);

namespace App\Interfaces;

use App\Data\Leave\CarryOverResult;
use App\Models\EmployeeLeaveBalance;
use Illuminate\Support\Collection;

interface LeaveBalanceRepositoryInterface
{
    public function employeeExistsInCompany(int $companyId, int $employeeId): bool;

    /**
     * @return Collection<int, EmployeeLeaveBalance>
     */
    public function findByEmployeeYear(int $companyId, int $employeeId, int $year): Collection;

    public function saveCarryOverResult(
        int $companyId,
        int $employeeId,
        int $year,
        string $leaveType,
        CarryOverResult $result,
    ): EmployeeLeaveBalance;

    public function updateRemainingMinutes(
        int $companyId,
        int $employeeId,
        int $year,
        string $leaveType,
        int $remainingMinutes,
    ): EmployeeLeaveBalance;
}
