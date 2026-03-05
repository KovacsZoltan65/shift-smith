<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Company;
use App\Models\User;
use App\Services\Selectors\CompanySelectorService;

final class CompanyContextService
{
    public function __construct(
        private readonly CompanySelectorService $companySelectorService,
    ) {}

    /**
     * @return array<int, array{id:int, name:string}>
     */
    public function selectableCompanies(User $user): array
    {
        $items = $this->companySelectorService->listSelectableCompaniesForUser($user);

        return array_values(array_map(
            static fn (array $row): array => [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
            ],
            $items
        ));
    }

    public function countSelectableCompanies(User $user): int
    {
        return $this->companySelectorService->countSelectableCompaniesForUser($user);
    }

    public function countSelectableCompaniesForSwitch(User $user): int
    {
        return $this->companySelectorService->countSelectableCompaniesForUser(
            $user,
            $this->isSuperadmin($user)
        );
    }

    public function firstSelectableCompanyId(User $user): ?int
    {
        return $this->companySelectorService->firstSelectableCompanyIdForUser($user);
    }

    public function userCanSelectCompany(User $user, int $companyId): bool
    {
        return $this->companySelectorService->userCanSelectCompany($user, $companyId);
    }

    public function userCanSelectCompanyForSwitch(User $user, int $companyId): bool
    {
        return $this->companySelectorService->userCanSelectCompany(
            $user,
            $companyId,
            $this->isSuperadmin($user)
        );
    }

    public function isSuperadmin(User $user): bool
    {
        return $user->hasRole('superadmin');
    }

    public function findSelectableCompany(User $user, int $companyId): ?Company
    {
        $items = $this->companySelectorService->listSelectableCompaniesForUser($user);
        $row = collect($items)->firstWhere('id', $companyId);
        if (! is_array($row)) {
            return null;
        }

        $company = new Company();
        $company->id = (int) $row['id'];
        $company->name = (string) $row['name'];
        $company->tenant_group_id = (int) $row['tenant_group_id'];

        return $company;
    }

    public function tenantGroupIdForCompany(User $user, int $companyId): ?int
    {
        return $this->companySelectorService->tenantGroupIdForSelectableCompany($user, $companyId);
    }

    public function tenantGroupIdForCompanyForSwitch(User $user, int $companyId): ?int
    {
        return $this->companySelectorService->tenantGroupIdForSelectableCompany(
            $user,
            $companyId,
            $this->isSuperadmin($user)
        );
    }

    /**
     * @return array<int, array{id:int, name:string}>
     */
    public function selectableCompaniesForSwitch(User $user): array
    {
        $items = $this->companySelectorService->listSelectableCompaniesForUser(
            $user,
            $this->isSuperadmin($user)
        );

        return array_values(array_map(
            static fn (array $row): array => [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
            ],
            $items
        ));
    }
}
