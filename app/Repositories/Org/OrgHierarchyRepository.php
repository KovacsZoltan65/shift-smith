<?php

declare(strict_types=1);

namespace App\Repositories\Org;

use App\Models\Employee;
use App\Models\Org\EmployeeSupervisor;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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

    public function searchEmployeesForHierarchy(int $companyId, string $query, int $limit): array
    {
        $term = trim($query);
        if ($term === '') {
            return [];
        }

        $safeLimit = max(1, min($limit, 50));
        $needle = mb_strtolower($term, 'UTF-8');

        /** @var array<int, array{id:int, full_name:string, email:string|null, position:string|null}> $rows */
        $rows = Employee::query()
            ->leftJoin('positions', 'positions.id', '=', 'employees.position_id')
            ->where('employees.company_id', $companyId)
            ->where(function ($builder) use ($needle): void {
                $builder->whereRaw('LOWER(employees.first_name) like ?', ["%{$needle}%"])
                    ->orWhereRaw('LOWER(employees.last_name) like ?', ["%{$needle}%"])
                    ->orWhereRaw("LOWER(CONCAT(employees.last_name, ' ', employees.first_name)) like ?", ["%{$needle}%"])
                    ->orWhereRaw('LOWER(COALESCE(employees.email, \'\')) like ?', ["%{$needle}%"])
                    ->orWhereRaw('LOWER(COALESCE(positions.name, \'\')) like ?', ["%{$needle}%"]);
            })
            ->orderBy('employees.last_name')
            ->orderBy('employees.first_name')
            ->orderBy('employees.id')
            ->limit($safeLimit)
            ->get([
                'employees.id',
                'employees.first_name',
                'employees.last_name',
                'employees.email',
                DB::raw('positions.name as position_name'),
            ])
            ->map(static function ($row): array {
                $fullName = trim(((string) ($row->last_name ?? '')).' '.((string) ($row->first_name ?? '')));
                if ($fullName === '') {
                    $fullName = '#'.(string) $row->id;
                }

                return [
                    'id' => (int) $row->id,
                    'full_name' => $fullName,
                    'email' => $row->email !== null ? (string) $row->email : null,
                    'position' => $row->position_name !== null ? (string) $row->position_name : null,
                ];
            })
            ->values()
            ->all();

        return $rows;
    }

    public function findActiveSupervisorEmployeeId(int $companyId, int $employeeId, CarbonInterface $atDate): ?int
    {
        $day = CarbonImmutable::instance($atDate)->toDateString();

        $supervisorId = EmployeeSupervisor::query()
            ->where('company_id', $companyId)
            ->where('employee_id', $employeeId)
            ->whereDate('valid_from', '<=', $day)
            ->where(function ($query) use ($day): void {
                $query->whereNull('valid_to')
                    ->orWhereDate('valid_to', '>=', $day);
            })
            ->orderByDesc('valid_from')
            ->orderByDesc('id')
            ->value('supervisor_employee_id');

        if (! is_numeric($supervisorId)) {
            return null;
        }

        return (int) $supervisorId;
    }

    public function getEmployeesByIdsInCompany(int $companyId, array $employeeIds): Collection
    {
        if ($employeeIds === []) {
            /** @var Collection<int, Employee> $empty */
            $empty = collect();
            return $empty;
        }

        /** @var Collection<int, Employee> $rows */
        $rows = Employee::query()
            ->where('company_id', $companyId)
            ->whereIn('id', $employeeIds)
            ->get(['id', 'first_name', 'last_name']);

        return $rows;
    }
}
