<?php

declare(strict_types=1);

namespace App\Services\Employees;

use App\Data\Employee\EmployeeLeaveProfileDTO;
use App\Interfaces\EmployeeRepositoryInterface;
use App\Interfaces\EmployeeProfileRepositoryInterface;
use App\Services\Cache\CacheVersionService;
use App\Services\Company\CurrentCompanyResolver;
use DomainException;

final class EmployeeLeaveProfileService
{
    public function __construct(
        private readonly EmployeeRepositoryInterface $employees,
        private readonly EmployeeProfileRepositoryInterface $profiles,
        private readonly CurrentCompanyResolver $currentCompanyResolver,
        private readonly CacheVersionService $cacheVersionService,
    ) {
    }

    public function show(int $employeeId): EmployeeLeaveProfileDTO
    {
        $companyId = $this->requireCurrentCompanyId();
        $this->requireEmployeeInCurrentCompany($employeeId, $companyId);

        $profile = $this->profiles->findByEmployeeInCompany($companyId, $employeeId);

        return $profile ?? new EmployeeLeaveProfileDTO(
            employee_id: $employeeId,
            company_id: $companyId,
            birth_date: null,
            children_count: 0,
            disabled_children_count: 0,
            is_disabled: false,
        );
    }

    /**
     * @param array{
     *   birth_date?: string|null,
     *   children_count:int,
     *   disabled_children_count:int,
     *   is_disabled:bool
     * } $attributes
     */
    public function update(int $employeeId, array $attributes): EmployeeLeaveProfileDTO
    {
        $companyId = $this->requireCurrentCompanyId();
        $this->requireEmployeeInCurrentCompany($employeeId, $companyId);

        if ((int) $attributes['disabled_children_count'] > (int) $attributes['children_count']) {
            throw new DomainException('A fogyatékos gyermekek száma nem lehet több az összes gyermek számánál.');
        }

        $profile = $this->profiles->upsertForEmployeeInCompany($companyId, $employeeId, $attributes);

        $this->cacheVersionService->bump("leave_entitlement:{$companyId}:{$employeeId}:all");

        return $profile;
    }

    private function requireCurrentCompanyId(): int
    {
        $companyId = $this->currentCompanyResolver->resolveCompanyId();

        if ($companyId === null) {
            throw new DomainException('Current company context is required for employee leave profile operations.');
        }

        return $companyId;
    }

    private function requireEmployeeInCurrentCompany(int $employeeId, int $companyId): void
    {
        if ($this->employees->findByIdInCompany($employeeId, $companyId) !== null) {
            return;
        }

        throw new DomainException('The employee is not available in the current company scope.');
    }
}
