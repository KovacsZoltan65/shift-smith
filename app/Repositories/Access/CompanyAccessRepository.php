<?php

declare(strict_types=1);

namespace App\Repositories\Access;

use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class CompanyAccessRepository
{
    /**
     * @return list<int>
     */
    public function accessibleCompanyIdsInTenant(User $user, int $tenantGroupId): array
    {
        if ($user->hasRole('superadmin')) {
            return Company::query()
                ->where('tenant_group_id', $tenantGroupId)
                ->pluck('id')
                ->map(static fn ($id): int => (int) $id)
                ->values()
                ->all();
        }

        return Company::query()
            ->where('tenant_group_id', $tenantGroupId)
            ->where('active', true)
            ->whereHas('users', function (Builder $userQuery) use ($user): void {
                $userQuery->whereKey((int) $user->id);
            })
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    public function employeeBelongsToCompanyInTenant(int $employeeId, int $companyId, int $tenantGroupId): bool
    {
        return Employee::query()
            ->whereKey($employeeId)
            ->whereHas('companies', function (Builder $companyQuery) use ($companyId, $tenantGroupId): void {
                $companyQuery
                    ->where('companies.id', $companyId)
                    ->where('companies.tenant_group_id', $tenantGroupId)
                    ->where('company_employee.active', true);
            })
            ->exists();
    }
}
