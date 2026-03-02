<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\Leave\CarryOverResult;
use App\Interfaces\LeaveBalanceRepositoryInterface;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeLeaveBalance;
use App\Services\Cache\CacheVersionService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LeaveBalanceRepository implements LeaveBalanceRepositoryInterface
{
    public function __construct(
        private readonly CacheVersionService $cacheVersionService,
    ) {
    }

    public function employeeExistsInCompany(int $companyId, int $employeeId): bool
    {
        return Employee::query()
            ->whereKey($employeeId)
            ->where('company_id', $companyId)
            ->whereHas('company', fn ($query) => $this->scopeCompanyToCurrentTenant($query, $companyId))
            ->exists();
    }

    public function findByEmployeeYear(int $companyId, int $employeeId, int $year): Collection
    {
        return EmployeeLeaveBalance::query()
            ->where('company_id', $companyId)
            ->where('employee_id', $employeeId)
            ->where('year', $year)
            ->whereHas('company', fn ($query) => $this->scopeCompanyToCurrentTenant($query, $companyId))
            ->orderBy('leave_type')
            ->get();
    }

    public function saveCarryOverResult(
        int $companyId,
        int $employeeId,
        int $year,
        string $leaveType,
        CarryOverResult $result,
    ): EmployeeLeaveBalance {
        /** @var EmployeeLeaveBalance $balance */
        $balance = EmployeeLeaveBalance::query()
            ->where('company_id', $companyId)
            ->where('employee_id', $employeeId)
            ->where('year', $year)
            ->where('leave_type', $leaveType)
            ->whereHas('company', fn ($query) => $this->scopeCompanyToCurrentTenant($query, $companyId))
            ->firstOrFail();

        $balance->carried_over_minutes = $result->transferable_minutes;
        $balance->carryover_valid_until = $result->valid_until;
        $balance->rule_applied = $result->rule_applied;
        $balance->save();

        return $balance->refresh();
    }

    public function updateRemainingMinutes(
        int $companyId,
        int $employeeId,
        int $year,
        string $leaveType,
        int $remainingMinutes,
    ): EmployeeLeaveBalance {
        /** @var EmployeeLeaveBalance $balance */
        $balance = EmployeeLeaveBalance::query()
            ->where('company_id', $companyId)
            ->where('employee_id', $employeeId)
            ->where('year', $year)
            ->where('leave_type', $leaveType)
            ->whereHas('company', fn ($query) => $this->scopeCompanyToCurrentTenant($query, $companyId))
            ->firstOrFail();

        $balance->remaining_minutes = $remainingMinutes;
        $balance->save();

        $this->bumpCarryOverVersion($companyId, $employeeId, $year);

        return $balance->refresh();
    }

    private function bumpCarryOverVersion(int $companyId, int $employeeId, int $year): void
    {
        DB::afterCommit(function () use ($companyId, $employeeId, $year): void {
            $this->cacheVersionService->bump("leave_carryover:{$companyId}:{$employeeId}:{$year}");
        });
    }

    private function scopeCompanyToCurrentTenant(mixed $query, int $companyId): void
    {
        $currentTenantId = \App\Models\TenantGroup::current()?->id;

        $query->whereKey($companyId)
            ->where('active', true);

        if (is_numeric($currentTenantId)) {
            $query->where('tenant_group_id', (int) $currentTenantId);
        } else {
            $query->whereRaw('1 = 0');
        }
    }
}
