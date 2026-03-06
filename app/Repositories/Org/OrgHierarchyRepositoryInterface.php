<?php

declare(strict_types=1);

namespace App\Repositories\Org;

use App\Models\Employee;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

interface OrgHierarchyRepositoryInterface
{
    public function findCeo(int $companyId): ?Employee;

    public function findEmployeeInCompany(int $companyId, int $employeeId): ?Employee;

    /**
     * @return Collection<int, Employee>
     */
    public function listDirectSubordinates(int $companyId, int $supervisorEmployeeId, CarbonInterface $atDate): Collection;

    /**
     * @param list<int> $supervisorEmployeeIds
     * @return array<int, int>
     */
    public function getDirectSubordinateCounts(int $companyId, array $supervisorEmployeeIds, CarbonInterface $atDate): array;

    /**
     * @param list<int> $employeeIds
     * @return array<int, bool>
     */
    public function getActiveSupervisorFlags(int $companyId, array $employeeIds, CarbonInterface $atDate): array;

    /**
     * @return array<int, array{id:int, full_name:string, email:string|null, position:string|null}>
     */
    public function searchEmployeesForHierarchy(int $companyId, string $query, int $limit): array;

    public function findActiveSupervisorEmployeeId(int $companyId, int $employeeId, CarbonInterface $atDate): ?int;

    /**
     * @param list<int> $employeeIds
     * @return Collection<int, Employee>
     */
    public function getEmployeesByIdsInCompany(int $companyId, array $employeeIds): Collection;

    /**
     * @return Collection<int, Employee>
     */
    public function listEmployeesInCompany(int $companyId): Collection;
}
