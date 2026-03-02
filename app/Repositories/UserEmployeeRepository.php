<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Company;
use App\Models\Employee;
use App\Models\TenantGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class UserEmployeeRepository implements UserEmployeeRepositoryInterface
{
    public function listUsersForTenant(User $actor): Collection
    {
        $tenantGroupId = $this->currentTenantGroupId();
        if ($tenantGroupId === null) {
            return collect();
        }

        $query = User::query()
            ->whereHas('companies', fn (Builder $q): Builder => $q->where('companies.tenant_group_id', $tenantGroupId))
            ->orderBy('name')
            ->orderBy('id');

        if (! $actor->hasRole('superadmin')) {
            $actorCompanyIds = $this->accessibleCompanyIdsForActor($actor, $tenantGroupId);
            if ($actorCompanyIds === []) {
                return collect();
            }

            $query->whereHas('companies', fn (Builder $q): Builder => $q->whereIn('companies.id', $actorCompanyIds));
        }

        return $query->get(['users.id', 'users.name', 'users.email']);
    }

    public function getUserEmployees(User $user): Collection
    {
        $tenantGroupId = $this->currentTenantGroupId();
        if ($tenantGroupId === null) {
            return collect();
        }

        return Employee::query()
            ->where('employees.active', true)
            ->whereHas('users', function (Builder $userQuery) use ($user): void {
                $userQuery
                    ->whereKey((int) $user->id)
                    ->where('user_employee.active', true);
            })
            ->whereHas('companies', function (Builder $companyQuery) use ($tenantGroupId): void {
                $companyQuery
                    ->where('companies.tenant_group_id', $tenantGroupId)
                    ->where('companies.active', true)
                    ->where('company_employee.active', true);
            })
            ->with(['companies' => function ($companyQuery) use ($tenantGroupId): void {
                $companyQuery
                    ->select(['companies.id', 'companies.name', 'companies.tenant_group_id'])
                    ->where('companies.tenant_group_id', $tenantGroupId)
                    ->where('companies.active', true)
                    ->where('company_employee.active', true);
            }])
            ->orderBy('employees.first_name')
            ->orderBy('employees.last_name')
            ->orderBy('employees.id')
            ->get();
    }

    public function getSelectableEmployeesForUser(User $actor, User $target): Collection
    {
        $tenantGroupId = $this->currentTenantGroupId();
        if ($tenantGroupId === null) {
            return collect();
        }

        $query = $this->selectableEmployeesBaseQuery($actor, $target, $tenantGroupId);

        return $query
            ->with(['companies' => function ($companyQuery) use ($tenantGroupId): void {
                $companyQuery
                    ->select(['companies.id', 'companies.name', 'companies.tenant_group_id'])
                    ->where('companies.tenant_group_id', $tenantGroupId)
                    ->where('companies.active', true)
                    ->where('company_employee.active', true);
            }])
            ->orderBy('employees.first_name')
            ->orderBy('employees.last_name')
            ->orderBy('employees.id')
            ->get();
    }

    public function employeeIsAssignableToUser(User $actor, User $target, Employee $employee): bool
    {
        $tenantGroupId = $this->currentTenantGroupId();
        if ($tenantGroupId === null) {
            return false;
        }

        return $this->selectableEmployeesBaseQuery($actor, $target, $tenantGroupId)
            ->whereKey((int) $employee->id)
            ->exists();
    }

    public function employeeIsManageableByActor(User $actor, Employee $employee): bool
    {
        $tenantGroupId = $this->currentTenantGroupId();
        if ($tenantGroupId === null) {
            return false;
        }

        $query = Employee::query()
            ->whereKey((int) $employee->id)
            ->where('employees.active', true)
            ->whereHas('companies', function (Builder $companyQuery) use ($tenantGroupId): void {
                $companyQuery
                    ->where('companies.tenant_group_id', $tenantGroupId)
                    ->where('companies.active', true)
                    ->where('company_employee.active', true);
            });

        if ($actor->hasRole('superadmin')) {
            return $query->exists();
        }

        $actorCompanyIds = $this->accessibleCompanyIdsForActor($actor, $tenantGroupId);
        if ($actorCompanyIds === []) {
            return false;
        }

        return $query
            ->whereHas('companies', function (Builder $companyQuery) use ($tenantGroupId, $actorCompanyIds): void {
                $companyQuery
                    ->where('companies.tenant_group_id', $tenantGroupId)
                    ->where('companies.active', true)
                    ->where('company_employee.active', true)
                    ->whereIn('companies.id', $actorCompanyIds);
            })
            ->exists();
    }

    public function userHasEmployee(User $target, Employee $employee): bool
    {
        return $target->employees()
            ->whereKey((int) $employee->id)
            ->where('user_employee.active', true)
            ->exists();
    }

    public function attach(User $target, Employee $employee): void
    {
        $target->employees()->syncWithoutDetaching([
            (int) $employee->id => ['active' => true],
        ]);

        $target->employees()->updateExistingPivot((int) $employee->id, [
            'active' => true,
        ]);
    }

    public function detach(User $target, Employee $employee): void
    {
        $target->employees()->detach((int) $employee->id);
    }

    /**
     * @return Builder<Employee>
     */
    private function selectableEmployeesBaseQuery(User $actor, User $target, int $tenantGroupId): Builder
    {
        $assignedEmployeeIds = $this->getUserEmployees($target)
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->values()
            ->all();

        $query = Employee::query()
            ->where('employees.active', true)
            ->whereHas('companies', function (Builder $companyQuery) use ($tenantGroupId): void {
                $companyQuery
                    ->where('companies.tenant_group_id', $tenantGroupId)
                    ->where('companies.active', true)
                    ->where('company_employee.active', true);
            });

        if ($assignedEmployeeIds !== []) {
            $query->whereNotIn('employees.id', $assignedEmployeeIds);
        }

        if ($actor->hasRole('superadmin')) {
            return $query;
        }

        $actorCompanyIds = $this->accessibleCompanyIdsForActor($actor, $tenantGroupId);
        if ($actorCompanyIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('companies', function (Builder $companyQuery) use ($tenantGroupId, $actorCompanyIds): void {
            $companyQuery
                ->where('companies.tenant_group_id', $tenantGroupId)
                ->where('companies.active', true)
                ->where('company_employee.active', true)
                ->whereIn('companies.id', $actorCompanyIds);
        });
    }

    /**
     * @return list<int>
     */
    private function accessibleCompanyIdsForActor(User $actor, int $tenantGroupId): array
    {
        if ($actor->hasRole('superadmin')) {
            return Company::query()
                ->where('tenant_group_id', $tenantGroupId)
                ->where('active', true)
                ->pluck('id')
                ->map(static fn ($id): int => (int) $id)
                ->values()
                ->all();
        }

        return Company::query()
            ->where('tenant_group_id', $tenantGroupId)
            ->where('active', true)
            ->whereHas('employees', function (Builder $employeeQuery) use ($actor): void {
                $employeeQuery
                    ->where('company_employee.active', true)
                    ->whereHas('users', function (Builder $userQuery) use ($actor): void {
                        $userQuery
                            ->whereKey((int) $actor->id)
                            ->where('user_employee.active', true);
                    });
            })
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    private function currentTenantGroupId(): ?int
    {
        $tenantGroupId = TenantGroup::current()?->id;
        if (! is_numeric($tenantGroupId)) {
            return null;
        }

        $value = (int) $tenantGroupId;

        return $value > 0 ? $value : null;
    }
}
