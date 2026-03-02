<?php

declare(strict_types=1);

namespace App\Repositories\UserAssignments;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Employee;
use App\Models\TenantGroup;
use App\Models\User;
use App\Models\UserEmployee;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class UserAssignmentRepository implements UserAssignmentRepositoryInterface
{
    public function fetchUsers(?string $q = null, int $perPage = 15): LengthAwarePaginator
    {
        $tenantGroupId = $this->currentTenantGroupId();

        return User::query()
            ->with('roles:id,name')
            ->where(function (Builder $builder) use ($tenantGroupId): void {
                $builder
                    ->whereHas('companies', function (Builder $companyQuery) use ($tenantGroupId): void {
                        $companyQuery->where('companies.tenant_group_id', $tenantGroupId);
                    })
                    ->orWhereHas('roles', fn (Builder $roleQuery) => $roleQuery->where('name', 'superadmin'));
            })
            ->when($q !== null && $q !== '', fn (Builder $builder) => $builder->whereLike(['name', 'email'], $q))
            ->orderBy('users.name')
            ->orderBy('users.id')
            ->paginate(max(1, min($perPage, 100)));
    }

    public function getTenantCompanies(?User $actor = null): Collection
    {
        $tenantGroupId = $this->currentTenantGroupId();
        return Company::query()
            ->where('companies.tenant_group_id', $tenantGroupId)
            ->where('companies.active', true)
            ->orderBy('companies.name')
            ->orderBy('companies.id')
            ->get(['companies.id', 'companies.name', 'companies.tenant_group_id', 'companies.active']);
    }

    public function getUserCompanies(User $user, ?User $actor = null): Collection
    {
        $tenantGroupId = $this->currentTenantGroupId();
        return Company::query()
            ->where('companies.tenant_group_id', $tenantGroupId)
            ->where('companies.active', true)
            ->whereHas('users', fn (Builder $builder) => $builder->whereKey((int) $user->id))
            ->orderBy('companies.name')
            ->orderBy('companies.id')
            ->get(['companies.id', 'companies.name', 'companies.tenant_group_id', 'companies.active']);
    }

    public function attachCompany(User $user, Company $company): void
    {
        CompanyUser::query()->firstOrCreate([
            'user_id' => (int) $user->id,
            'company_id' => (int) $company->id,
        ]);
    }

    public function detachCompany(User $user, Company $company): void
    {
        $this->removeEmployee($user, $company);

        CompanyUser::query()
            ->where('user_id', (int) $user->id)
            ->where('company_id', (int) $company->id)
            ->delete();
    }

    public function getCompanyEmployees(Company $company): Collection
    {
        return Employee::query()
            ->where('employees.active', true)
            ->whereHas('companies', function (Builder $builder) use ($company): void {
                $builder
                    ->where('companies.id', (int) $company->id)
                    ->where('companies.tenant_group_id', $this->currentTenantGroupId())
                    ->where('companies.active', true)
                    ->where('company_employee.active', true);
            })
            ->orderBy('employees.first_name')
            ->orderBy('employees.last_name')
            ->orderBy('employees.id')
            ->get(['employees.id', 'employees.first_name', 'employees.last_name', 'employees.email', 'employees.active']);
    }

    public function getAssignedEmployee(User $user, Company $company): ?Employee
    {
        $assignment = UserEmployee::query()
            ->with(['employee:id,first_name,last_name,email,active', 'company:id,tenant_group_id'])
            ->where('user_id', (int) $user->id)
            ->where('company_id', (int) $company->id)
            ->first();

        if ($assignment === null || $assignment->company === null) {
            return null;
        }

        return (int) $assignment->company->tenant_group_id === $this->currentTenantGroupId()
            ? $assignment->employee
            : null;
    }

    public function assignEmployee(User $user, Company $company, Employee $employee): void
    {
        UserEmployee::query()->updateOrCreate(
            [
                'user_id' => (int) $user->id,
                'company_id' => (int) $company->id,
            ],
            [
                'employee_id' => (int) $employee->id,
                'active' => true,
            ]
        );
    }

    public function removeEmployee(User $user, Company $company): void
    {
        UserEmployee::query()
            ->where('user_id', (int) $user->id)
            ->where('company_id', (int) $company->id)
            ->delete();
    }

    public function employeeAssignedToOtherUser(User $user, Company $company, Employee $employee): bool
    {
        return UserEmployee::query()
            ->where('company_id', (int) $company->id)
            ->where('employee_id', (int) $employee->id)
            ->where('user_id', '!=', (int) $user->id)
            ->exists();
    }

    public function findTenantCompanyById(int $companyId, ?User $actor = null): ?Company
    {
        $tenantGroupId = $this->currentTenantGroupId();
        return Company::query()
            ->whereKey($companyId)
            ->where('companies.tenant_group_id', $tenantGroupId)
            ->where('companies.active', true)
            ->first(['companies.id', 'companies.name', 'companies.tenant_group_id', 'companies.active']);
    }

    public function findCompanyEmployeeById(Company $company, int $employeeId): ?Employee
    {
        return Employee::query()
            ->whereKey($employeeId)
            ->where('employees.active', true)
            ->whereHas('companies', function (Builder $builder) use ($company): void {
                $builder
                    ->where('companies.id', (int) $company->id)
                    ->where('companies.tenant_group_id', $this->currentTenantGroupId())
                    ->where('companies.active', true)
                    ->where('company_employee.active', true);
            })
            ->first(['employees.id', 'employees.first_name', 'employees.last_name', 'employees.email', 'employees.active']);
    }

    public function userHasCompany(User $user, Company $company): bool
    {
        return CompanyUser::query()
            ->where('user_id', (int) $user->id)
            ->where('company_id', (int) $company->id)
            ->exists();
    }

    public function userIsVisibleInTenant(User $user, ?User $actor = null): bool
    {
        if ($user->hasRole('superadmin')) {
            return true;
        }

        return $user->companies()
            ->where('companies.tenant_group_id', $this->currentTenantGroupId())
            ->exists();
    }

    private function currentTenantGroupId(): int
    {
        $tenantGroupId = TenantGroup::current()?->id;

        if (! is_numeric($tenantGroupId)) {
            throw ValidationException::withMessages([
                'tenant_group_id' => 'Nincs aktív tenant group kontextus.',
            ]);
        }

        return (int) $tenantGroupId;
    }
}
