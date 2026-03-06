<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Org\EmployeeSupervisor;
use Carbon\CarbonInterface;

interface EmployeeSupervisorRepositoryInterface
{
    public function findActiveSupervisor(int $companyId, int $employeeId, CarbonInterface $date): ?EmployeeSupervisor;

    /**
     * @return list<int>
     */
    public function listDirectSubordinates(int $companyId, int $supervisorEmployeeId, CarbonInterface $date): array;

    /**
     * @return list<int>
     */
    public function listSubtreeEmployeeIds(int $companyId, int $supervisorEmployeeId, CarbonInterface $date): array;

    public function hasOverlappingSupervisorPeriod(
        int $companyId,
        int $employeeId,
        CarbonInterface $from,
        ?CarbonInterface $to = null,
        ?int $ignoreId = null
    ): bool;

    public function wouldCreateCycle(
        int $companyId,
        int $employeeId,
        int $supervisorEmployeeId,
        CarbonInterface $date
    ): bool;

    public function closeActivePeriod(int $companyId, int $employeeId, CarbonInterface $newValidFrom): ?EmployeeSupervisor;

    public function createNewRelation(
        int $companyId,
        int $employeeId,
        int $supervisorEmployeeId,
        CarbonInterface $validFrom,
        ?int $createdByUserId = null
    ): EmployeeSupervisor;

    /**
     * @return list<array{employee_id:int,supervisor_employee_id:int}>
     */
    public function listActiveRelations(int $companyId, CarbonInterface $date): array;

    /**
     * @return array<int, list<int>>
     */
    public function listActiveSupervisorIdsGroupedByEmployee(int $companyId, CarbonInterface $date): array;

    /**
     * @return list<array{id:int,employee_id:int,supervisor_employee_id:int,valid_from:string,valid_to:?string}>
     */
    public function listCompanyHistoryRows(int $companyId): array;

    /**
     * @return list<EmployeeSupervisor>
     */
    public function listSupervisorHistory(int $companyId, int $employeeId): array;
}
