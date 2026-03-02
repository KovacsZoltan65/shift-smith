<?php

declare(strict_types=1);

namespace App\Services\Leave;

use App\Data\Leave\AnnualLeaveEntitlementResult;
use App\Interfaces\EmployeeRepositoryInterface;
use App\Services\Company\CurrentCompanyResolver;
use DomainException;

final class EmployeeLeaveEntitlementService
{
    public function __construct(
        private readonly EmployeeRepositoryInterface $employees,
        private readonly CurrentCompanyResolver $currentCompanyResolver,
        private readonly LeaveEntitlementCalculator $calculator,
    ) {
    }

    public function showForEmployee(int $employeeId, int $year): AnnualLeaveEntitlementResult
    {
        $companyId = $this->currentCompanyResolver->resolveCompanyId();

        if ($companyId === null) {
            throw new DomainException('Current company context is required for leave entitlement queries.');
        }

        $employee = $this->employees->findByIdInCompany($employeeId, $companyId);

        if ($employee === null) {
            throw new DomainException('The employee is not available in the current company scope.');
        }

        return $this->calculator->calculateAnnualMinutesForEmployee($employeeId, $year);
    }
}
