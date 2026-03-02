<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Employee;
use App\Models\TenantGroup;
use App\Models\User;
use App\Models\UserEmployee;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class UserAssignmentRepository
{
    /**
     * @return Collection<int, User>
     */
    public function listUsersForTenant(User $actor): Collection
    {
        $tenantGroupId = $this->currentTenantGroupId();
        if ($tenantGroupId === null) {
            return collect();
        }

        $query = User::query()
            ->whereHas('companies', fn (Builder $q) => $q->where('companies.tenant_group_id', $tenantGroupId))
            ->orderBy('users.name')
            ->orderBy('users.id');

        if (! $actor->hasRole('superadmin')) {
            $actorCompanyIds = $this->accessibleCompanyIdsForActor($actor, $tenantGroupId);
            if ($actorCompanyIds === []) {
                return collect();
            }

            $query->whereHas('companies', fn (Builder $q) => $q->whereIn('companies.id', $actorCompanyIds));
        }

        return $query
            ->distinct()
            ->get(['users.id', 'users.name', 'users.email']);
    }

    public function userIsManageable(User $actor, User $target): bool
    {
        $tenantGroupId = $this->currentTenantGroupId();
        if ($tenantGroupId === null) {
            return false;
        }

        if ($actor->hasRole('superadmin')) {
            return $target->companies()
                ->where('companies.tenant_group_id', $tenantGroupId)
                ->exists();
        }

        $actorCompanyIds = $this->accessibleCompanyIdsForActor($actor, $tenantGroupId);
        if ($actorCompanyIds === []) {
            return false;
        }

        return $target->companies()
            ->where('companies.tenant_group_id', $tenantGroupId)
            ->whereIn('companies.id', $actorCompanyIds)
            ->exists();
    }

    /**
     * @return Collection<int, Company>
     */
    public function managedCompaniesForUser(User $actor, User $target): Collection
    {
        $tenantGroupId = $this->currentTenantGroupId();
        if ($tenantGroupId === null) {
            return collect();
        }

        $query = Company::query()
            ->where('companies.tenant_group_id', $tenantGroupId)
            ->where('companies.active', true)
            ->whereHas('users', fn (Builder $q) => $q->whereKey((int) $target->id))
            ->orderBy('companies.name')
            ->orderBy('companies.id');

        if (! $actor->hasRole('superadmin')) {
            $query->whereIn('companies.id', $this->accessibleCompanyIdsForActor($actor, $tenantGroupId));
        }

        return $query->get(['companies.id', 'companies.name', 'companies.tenant_group_id']);
    }

    /**
     * @return Collection<int, Company>
     */
    public function attachableCompaniesForUser(User $actor, User $target): Collection
    {
        $tenantGroupId = $this->currentTenantGroupId();
        if ($tenantGroupId === null) {
            return collect();
        }

        $attachedIds = $target->companies()
            ->where('companies.tenant_group_id', $tenantGroupId)
            ->pluck('companies.id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        $query = Company::query()
            ->where('companies.tenant_group_id', $tenantGroupId)
            ->where('companies.active', true)
            ->when($attachedIds !== [], fn (Builder $q) => $q->whereNotIn('companies.id', $attachedIds))
            ->orderBy('companies.name')
            ->orderBy('companies.id');

        if (! $actor->hasRole('superadmin')) {
            $query->whereIn('companies.id', $this->accessibleCompanyIdsForActor($actor, $tenantGroupId));
        }

        return $query->get(['companies.id', 'companies.name', 'companies.tenant_group_id']);
    }

    public function companyIsManageableByActor(User $actor, Company $company): bool
    {
        $tenantGroupId = $this->currentTenantGroupId();
        if ($tenantGroupId === null || (int) $company->tenant_group_id !== $tenantGroupId || ! $company->active) {
            return false;
        }

        if ($actor->hasRole('superadmin')) {
            return true;
        }

        return \in_array((int) $company->id, $this->accessibleCompanyIdsForActor($actor, $tenantGroupId), true);
    }

    public function userHasCompany(User $target, Company $company): bool
    {
        return CompanyUser::query()
            ->where('user_id', (int) $target->id)
            ->where('company_id', (int) $company->id)
            ->exists();
    }

    public function attachCompany(User $target, Company $company): void
    {
        CompanyUser::query()->firstOrCreate([
            'user_id' => (int) $target->id,
            'company_id' => (int) $company->id,
        ]);
    }

    public function detachCompany(User $target, Company $company): void
    {
        UserEmployee::query()
            ->where('user_id', (int) $target->id)
            ->where('company_id', (int) $company->id)
            ->get()
            ->each
            ->delete();

        CompanyUser::query()
            ->where('user_id', (int) $target->id)
            ->where('company_id', (int) $company->id)
            ->get()
            ->each
            ->delete();
    }

    public function currentAssignment(User $target, Company $company): ?UserEmployee
    {
        return UserEmployee::query()
            ->with(['employee:id,first_name,last_name,email,active', 'company:id,name,tenant_group_id'])
            ->where('user_id', (int) $target->id)
            ->where('company_id', (int) $company->id)
            ->where('active', true)
            ->first();
    }

    /**
     * @return Collection<int, Employee>
     */
    public function selectableEmployeesForCompany(User $actor, Company $company): Collection
    {
        if (! $this->companyIsManageableByActor($actor, $company)) {
            return collect();
        }

        return Employee::query()
            ->where('employees.active', true)
            ->whereHas('companies', function (Builder $companyQuery) use ($company): void {
                $companyQuery
                    ->where('companies.id', (int) $company->id)
                    ->where('companies.active', true)
                    ->where('company_employee.active', true);
            })
            ->with(['companies' => function (Builder $companyQuery) use ($company): void {
                $companyQuery
                    ->select(['companies.id', 'companies.name', 'companies.tenant_group_id'])
                    ->where('companies.id', (int) $company->id)
                    ->where('companies.active', true)
                    ->where('company_employee.active', true);
            }])
            ->orderBy('employees.first_name')
            ->orderBy('employees.last_name')
            ->orderBy('employees.id')
            ->get(['employees.id', 'employees.first_name', 'employees.last_name', 'employees.email', 'employees.active']);
    }

    public function employeeIsAssignableToCompany(User $actor, Company $company, Employee $employee): bool
    {
        if (! $this->companyIsManageableByActor($actor, $company)) {
            return false;
        }

        return Employee::query()
            ->whereKey((int) $employee->id)
            ->where('employees.active', true)
            ->whereHas('companies', function (Builder $companyQuery) use ($company): void {
                $companyQuery
                    ->where('companies.id', (int) $company->id)
                    ->where('companies.active', true)
                    ->where('company_employee.active', true);
            })
            ->exists();
    }

    public function assignEmployee(User $target, Company $company, Employee $employee): void
    {
        UserEmployee::query()->updateOrCreate(
            [
                'user_id' => (int) $target->id,
                'company_id' => (int) $company->id,
            ],
            [
                'employee_id' => (int) $employee->id,
                'active' => true,
            ]
        );
    }

    public function removeEmployee(User $target, Company $company): void
    {
        UserEmployee::query()
            ->where('user_id', (int) $target->id)
            ->where('company_id', (int) $company->id)
            ->get()
            ->each
            ->delete();
    }

    /**
     * @return list<int>
     */
    private function accessibleCompanyIdsForActor(User $actor, int $tenantGroupId): array
    {
        return Company::query()
            ->where('tenant_group_id', $tenantGroupId)
            ->where('active', true)
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
