<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class CompanyContextService
{
    /**
     * @return array<int, array{id:int, name:string}>
     */
    public function selectableCompanies(User $user): array
    {
        /** @var array<int, array{id:int, name:string}> $items */
        $items = $this->companiesQueryFor($user)
            ->select('companies.id', 'companies.name')
            ->orderBy('companies.name')
            ->get()
            ->map(static fn ($company): array => [
                'id' => (int) $company->id,
                'name' => (string) $company->name,
            ])
            ->values()
            ->all();

        return $items;
    }

    public function countSelectableCompanies(User $user): int
    {
        return (int) $this->companiesQueryFor($user)->count();
    }

    public function firstSelectableCompanyId(User $user): ?int
    {
        $id = $this->companiesQueryFor($user)
            ->orderBy('companies.id')
            ->value('companies.id');

        if (!is_numeric($id)) {
            return null;
        }

        $companyId = (int) $id;

        return $companyId > 0 ? $companyId : null;
    }

    public function userCanSelectCompany(User $user, int $companyId): bool
    {
        return $this->companiesQueryFor($user)
            ->where('companies.id', $companyId)
            ->exists();
    }

    public function isSuperadmin(User $user): bool
    {
        return $user->hasRole('superadmin');
    }

    public function findSelectableCompany(User $user, int $companyId): ?Company
    {
        /** @var Company|null $company */
        $company = $this->companiesQueryFor($user)
            ->where('companies.id', $companyId)
            ->first(['companies.id', 'companies.name']);

        return $company;
    }

    /**
     * @return Builder<Company>
     */
    private function companiesQueryFor(User $user): Builder
    {
        if ($this->isSuperadmin($user)) {
            return Company::query();
        }

        return $user->companies()->getQuery();
    }

    public function tenantGroupIdForCompany(User $user, int $companyId): ?int
    {
        $tenantGroupId = $this->companiesQueryFor($user)
            ->where('companies.id', $companyId)
            ->value('tenant_group_id');

        if (! is_numeric($tenantGroupId)) {
            return null;
        }

        $id = (int) $tenantGroupId;

        return $id > 0 ? $id : null;
    }
}
