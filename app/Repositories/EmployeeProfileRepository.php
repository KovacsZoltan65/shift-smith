<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\Employee\EmployeeLeaveProfileDTO;
use App\Interfaces\EmployeeProfileRepositoryInterface;
use App\Models\Employee;

final class EmployeeProfileRepository implements EmployeeProfileRepositoryInterface
{
    public function findByEmployeeInCompany(int $companyId, int $employeeId): EmployeeLeaveProfileDTO
    {
        /** @var Employee $employee */
        $employee = $this->scopedEmployeeQuery($companyId, $employeeId)->firstOrFail();

        return $this->toDto($employee);
    }

    public function upsertForEmployeeInCompany(int $companyId, int $employeeId, array $attributes): EmployeeLeaveProfileDTO
    {
        /** @var Employee $employee */
        $employee = $this->scopedEmployeeQuery($companyId, $employeeId)
            ->lockForUpdate()
            ->firstOrFail();

        $employee->birth_date = $attributes['birth_date'] ?? null;
        $employee->children_count = max(0, (int) $attributes['children_count']);
        $employee->disabled_children_count = max(0, (int) $attributes['disabled_children_count']);
        $employee->is_disabled = (bool) $attributes['is_disabled'];
        $employee->save();

        return $this->toDto($employee->refresh());
    }

    private function scopedEmployeeQuery(int $companyId, int $employeeId): \Illuminate\Database\Eloquent\Builder
    {
        $tenantGroupId = \App\Models\TenantGroup::current()?->id;

        return Employee::query()
            ->select([
                'employees.id',
                'employees.company_id',
                'employees.birth_date',
                'employees.children_count',
                'employees.disabled_children_count',
                'employees.is_disabled',
            ])
            ->whereKey($employeeId)
            ->where('employees.company_id', $companyId)
            ->whereHas('company', function ($query) use ($companyId, $tenantGroupId): void {
                $query->whereKey($companyId)->where('active', true);

                if (is_numeric($tenantGroupId)) {
                    $query->where('tenant_group_id', (int) $tenantGroupId);
                    return;
                }

                $query->whereRaw('1 = 0');
            });
    }

    private function toDto(Employee $employee): EmployeeLeaveProfileDTO
    {
        return new EmployeeLeaveProfileDTO(
            employee_id: (int) $employee->id,
            company_id: (int) $employee->company_id,
            birth_date: $employee->birth_date?->toDateString(),
            children_count: max(0, (int) $employee->children_count),
            disabled_children_count: max(0, (int) $employee->disabled_children_count),
            is_disabled: (bool) $employee->is_disabled,
        );
    }
}
