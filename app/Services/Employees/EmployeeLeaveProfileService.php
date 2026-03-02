<?php

declare(strict_types=1);

namespace App\Services\Employees;

use App\Data\Employee\EmployeeLeaveProfileDTO;
use App\Interfaces\EmployeeProfileRepositoryInterface;
use App\Services\Cache\CacheVersionService;
use App\Services\Company\CurrentCompanyResolver;
use DomainException;

final class EmployeeLeaveProfileService
{
    public function __construct(
        private readonly EmployeeProfileRepositoryInterface $profiles,
        private readonly CurrentCompanyResolver $currentCompanyResolver,
        private readonly CacheVersionService $cacheVersionService,
    ) {
    }

    public function show(int $employeeId): EmployeeLeaveProfileDTO
    {
        return $this->profiles->findByEmployeeInCompany(
            $this->requireCurrentCompanyId(),
            $employeeId,
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
}
