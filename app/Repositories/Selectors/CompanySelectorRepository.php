<?php

declare(strict_types=1);

namespace App\Repositories\Selectors;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class CompanySelectorRepository
{
    /**
     * @return Collection<int, Company>
     */
    public function listSelectableCompaniesForUser(User $user, int $tenantGroupId): Collection
    {
        return $this->baseQuery($user, $tenantGroupId)
            ->orderBy('companies.name')
            ->get(['companies.id', 'companies.name', 'companies.tenant_group_id']);
    }

    /**
     * @return Collection<int, Company>
     */
    public function listSelectableCompaniesForUserAcrossTenants(User $user): Collection
    {
        return $this->baseQuery($user, null)
            ->orderBy('companies.name')
            ->get(['companies.id', 'companies.name', 'companies.tenant_group_id']);
    }

    public function companyIsSelectableForUser(User $user, int $tenantGroupId, int $companyId): bool
    {
        return $this->baseQuery($user, $tenantGroupId)
            ->where('companies.id', $companyId)
            ->exists();
    }

    public function selectableCompanyCountForUser(User $user, int $tenantGroupId): int
    {
        return (int) $this->baseQuery($user, $tenantGroupId)->count();
    }

    public function companyIsSelectableForUserAcrossTenants(User $user, int $companyId): bool
    {
        return $this->baseQuery($user, null)
            ->where('companies.id', $companyId)
            ->exists();
    }

    public function firstSelectableCompanyIdForUser(User $user, int $tenantGroupId): ?int
    {
        $id = $this->baseQuery($user, $tenantGroupId)
            ->orderBy('companies.id')
            ->value('companies.id');

        if (! is_numeric($id)) {
            return null;
        }

        $value = (int) $id;

        return $value > 0 ? $value : null;
    }

    /**
     * @return Builder<Company>
     */
    private function baseQuery(User $user, ?int $tenantGroupId): Builder
    {
        $query = Company::query()
            ->when($tenantGroupId !== null, fn (Builder $q) => $q->where('companies.tenant_group_id', $tenantGroupId))
            ->where('companies.active', true);

        if ($user->hasRole('superadmin')) {
            return $query;
        }

        return $query->whereHas('users', function (Builder $userQuery) use ($user): void {
            $userQuery->whereKey((int) $user->id);
        });
    }
}
