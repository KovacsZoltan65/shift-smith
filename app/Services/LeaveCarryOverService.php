<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Leave\CarryOverResult;
use App\Data\Leave\LeaveBalanceContext;
use App\Interfaces\LeaveBalanceRepositoryInterface;
use App\Models\EmployeeLeaveBalance;
use App\Services\Company\CurrentCompanyResolver;
use App\Services\Leave\LeaveSettings;
use App\Services\Leave\LeaveCarryOverEngine;
use DomainException;

class LeaveCarryOverService
{
    public function __construct(
        private readonly LeaveBalanceRepositoryInterface $repository,
        private readonly LeaveCarryOverEngine $engine,
        private readonly CurrentCompanyResolver $currentCompanyResolver,
        private readonly CacheService $cacheService,
        private readonly \App\Services\Cache\CacheVersionService $cacheVersionService,
    ) {
    }

    /**
     * @return array<string, CarryOverResult>
     */
    public function calculateForEmployeeYear(int $employeeId, int $year): array
    {
        $companyId = $this->currentCompanyResolver->resolveCompanyId();

        if ($companyId === null) {
            throw new DomainException('Current company context is required for leave carry-over calculation.');
        }

        if (! $this->repository->employeeExistsInCompany($companyId, $employeeId)) {
            throw new DomainException('The employee is not available in the current company scope.');
        }

        $minutesPerDay = max(1, LeaveSettings::minutesPerDay());
        $version = $this->cacheVersionService->get("leave_carryover:{$companyId}:{$employeeId}:{$year}");

        /** @var array<string, CarryOverResult> */
        return $this->cacheService->remember(
            tag: "leave_carryover:{$companyId}:{$employeeId}:{$year}",
            key: "v{$version}:minutes_per_day:{$minutesPerDay}",
            callback: function () use ($companyId, $employeeId, $year): array {
                $results = [];

                foreach ($this->repository->findByEmployeeYear($companyId, $employeeId, $year) as $balance) {
                    $result = $this->engine->evaluate($this->toContext($balance));
                    $this->repository->saveCarryOverResult($companyId, $employeeId, $year, (string) $balance->leave_type, $result);
                    $results[(string) $balance->leave_type] = $result;
                }

                return $results;
            },
            ttl: (int) config('cache.ttl_fetch', 300),
        );
    }

    private function toContext(EmployeeLeaveBalance $balance): LeaveBalanceContext
    {
        return new LeaveBalanceContext(
            employee_id: (int) $balance->employee_id,
            company_id: (int) $balance->company_id,
            year: (int) $balance->year,
            remaining_minutes: (int) $balance->remaining_minutes,
            employment_start_date: $balance->employment_start_date?->toDateString(),
            leave_type: (string) $balance->leave_type,
            has_employer_exception: (bool) $balance->has_employer_exception,
            employee_blocked_periods: is_array($balance->employee_blocked_periods) ? $balance->employee_blocked_periods : [],
            agreement_age_bonus_transfer: (bool) $balance->agreement_age_bonus_transfer,
        );
    }
}
