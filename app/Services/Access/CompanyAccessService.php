<?php

declare(strict_types=1);

namespace App\Services\Access;

use App\Models\Employee;
use App\Models\TenantGroup;
use App\Models\User;
use App\Repositories\Access\CompanyAccessRepository;
use Illuminate\Database\Eloquent\Builder;

final class CompanyAccessService
{
    public function __construct(
        private readonly CompanyAccessRepository $repository,
    ) {}

    public function currentTenantGroupId(): ?int
    {
        $tenantId = TenantGroup::current()?->id;
        if (! is_numeric($tenantId)) {
            return null;
        }

        $value = (int) $tenantId;

        return $value > 0 ? $value : null;
    }

    /**
     * @return list<int>
     */
    public function accessibleCompanyIds(User $user): array
    {
        $tenantGroupId = $this->currentTenantGroupId();
        if ($tenantGroupId === null) {
            return [];
        }

        return $this->repository->accessibleCompanyIdsInTenant($user, $tenantGroupId);
    }

    public function userHasCompanyAccess(User $user, int $companyId): bool
    {
        return \in_array($companyId, $this->accessibleCompanyIds($user), true);
    }

    public function userCanAccessEmployee(User $user, Employee $employee): bool
    {
        $tenantGroupId = $this->currentTenantGroupId();
        if ($tenantGroupId === null) {
            return false;
        }

        $companyIds = $this->accessibleCompanyIds($user);
        if ($companyIds === []) {
            return false;
        }

        foreach ($companyIds as $companyId) {
            if ($this->repository->employeeBelongsToCompanyInTenant((int) $employee->id, $companyId, $tenantGroupId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param Builder<Employee> $query
     * @return Builder<Employee>
     */
    public function scopeEmployeesToCompany(Builder $query, int $companyId): Builder
    {
        $tenantGroupId = $this->currentTenantGroupId();
        if ($tenantGroupId === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('companies', function (Builder $companyQuery) use ($companyId, $tenantGroupId): void {
            $companyQuery
                ->where('companies.id', $companyId)
                ->where('companies.tenant_group_id', $tenantGroupId)
                ->where('companies.active', true)
                ->where('company_employee.active', true);
        });
    }
}
