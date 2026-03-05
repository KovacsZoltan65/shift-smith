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
}
