<?php

declare(strict_types=1);

namespace App\Repositories\Org;

use App\Models\Employee;
use App\Models\Org\EmployeeSupervisor;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

final class OrgHierarchyRepository implements OrgHierarchyRepositoryInterface
{
    public function findCeo(int $companyId): ?Employee
    {
        /** @var Employee|null $row */
        $row = Employee::query()
            ->with('position:id,name')
            ->where('company_id', $companyId)
            ->where('org_level', Employee::ORG_LEVEL_CEO)
            ->orderByRaw('hired_at IS NULL')
            ->orderBy('hired_at')
            ->orderBy('id')
            ->first();

        return $row;
    }

    public function findEmployeeInCompany(int $companyId, int $employeeId): ?Employee
    {
        /** @var Employee|null $row */
        $row = Employee::query()
            ->with('position:id,name')
            ->where('company_id', $companyId)
            ->whereKey($employeeId)
            ->first();

        return $row;
    }

    public function listDirectSubordinates(int $companyId, int $supervisorEmployeeId, CarbonInterface $atDate): Collection
    {
        $day = CarbonImmutable::instance($atDate)->toDateString();

        $ids = EmployeeSupervisor::query()
            ->where('company_id', $companyId)
            ->where('supervisor_employee_id', $supervisorEmployeeId)
            ->whereDate('valid_from', '<=', $day)
            ->where(function ($query) use ($day): void {
                $query->whereNull('valid_to')
                    ->orWhereDate('valid_to', '>=', $day);
            })
            ->pluck('employee_id')
            ->map(static fn ($id): int => (int) $id)
            ->values();

        if ($ids->isEmpty()) {
            /** @var Collection<int, Employee> $empty */
            $empty = collect();
            return $empty;
        }

        /** @var Collection<int, Employee> $rows */
        $rows = Employee::query()
            ->with('position:id,name')
            ->where('company_id', $companyId)
            ->whereIn('id', $ids->all())
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('id')
            ->get();

        return $rows;
    }

    public function getDirectSubordinateCounts(int $companyId, array $supervisorEmployeeIds, CarbonInterface $atDate): array
    {
        if ($supervisorEmployeeIds === []) {
            return [];
        }

        $day = CarbonImmutable::instance($atDate)->toDateString();

        /** @var Collection<int, object{supervisor_employee_id:int|string, aggregate:int|string}> $rows */
        $rows = EmployeeSupervisor::query()
            ->selectRaw('supervisor_employee_id, COUNT(*) as aggregate')
            ->where('company_id', $companyId)
            ->whereIn('supervisor_employee_id', $supervisorEmployeeIds)
            ->whereDate('valid_from', '<=', $day)
            ->where(function ($query) use ($day): void {
                $query->whereNull('valid_to')
                    ->orWhereDate('valid_to', '>=', $day);
            })
            ->groupBy('supervisor_employee_id')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row->supervisor_employee_id] = (int) $row->aggregate;
        }

        return $result;
    }
}
