<?php

declare(strict_types=1);

namespace App\Services\Selectors;

use App\Models\TenantGroup;
use App\Models\User;
use App\Policies\CompanyPolicy;
use App\Repositories\Selectors\CompanySelectorRepository;
use App\Services\Cache\CacheVersionService;
use App\Services\CacheService;

final class CompanySelectorService
{
    private const CACHE_TAG = 'selector';
    private const NS_SELECTORS_COMPANIES = 'selectors.companies';

    public function __construct(
        private readonly CompanySelectorRepository $repository,
        private readonly CacheService $cacheService,
        private readonly CacheVersionService $cacheVersions,
    ) {}

    /**
     * @return array<int, array{id:int,name:string,tenant_group_id:int}>
     */
    public function listSelectableCompaniesForUser(User $user): array
    {
        if (! $this->canAccessCompanySelection($user)) {
            return [];
        }

        $tenantGroupId = $this->currentTenantGroupId();
        $versionNamespace = $tenantGroupId === null
            ? "landlord:".self::NS_SELECTORS_COMPANIES
            : self::NS_SELECTORS_COMPANIES;
        $version = $this->cacheVersions->get($versionNamespace);
        $cacheKey = $tenantGroupId === null
            ? "landlord:selector:companies:user:{$user->id}:v{$version}"
            : "tenant:{$tenantGroupId}:selector:companies:user:{$user->id}:v{$version}";

        /** @var array<int, array{id:int,name:string,tenant_group_id:int}> $items */
        $items = $this->cacheService->remember(
            tag: self::CACHE_TAG,
            key: $cacheKey,
            callback: function () use ($user, $tenantGroupId): array {
                $collection = $tenantGroupId === null
                    ? $this->repository->listSelectableCompaniesForUserAcrossTenants($user)
                    : $this->repository->listSelectableCompaniesForUser($user, $tenantGroupId);

                return $collection
                    ->map(static fn ($company): array => [
                        'id' => (int) $company->id,
                        'name' => (string) $company->name,
                        'tenant_group_id' => (int) $company->tenant_group_id,
                    ])
                    ->values()
                    ->all();
            },
            ttl: (int) config('cache.ttl_fetch', 1800),
        );

        return $items;
    }

    public function countSelectableCompaniesForUser(User $user): int
    {
        return count($this->listSelectableCompaniesForUser($user));
    }

    public function firstSelectableCompanyIdForUser(User $user): ?int
    {
        $items = $this->listSelectableCompaniesForUser($user);
        if ($items === []) {
            return null;
        }

        return (int) $items[0]['id'];
    }

    public function userCanSelectCompany(User $user, int $companyId): bool
    {
        $tenantGroupId = $this->currentTenantGroupId();
        if (! $this->canAccessCompanySelection($user)) {
            return false;
        }

        if ($tenantGroupId === null) {
            return $this->repository->companyIsSelectableForUserAcrossTenants($user, $companyId);
        }

        return $this->repository->companyIsSelectableForUser($user, $tenantGroupId, $companyId);
    }

    public function tenantGroupIdForSelectableCompany(User $user, int $companyId): ?int
    {
        if (! $this->userCanSelectCompany($user, $companyId)) {
            return null;
        }

        $tenantGroupId = $this->currentTenantGroupId();
        if ($tenantGroupId !== null) {
            return $tenantGroupId;
        }

        $match = collect($this->listSelectableCompaniesForUser($user))->firstWhere('id', $companyId);
        if (! is_array($match) || ! is_numeric($match['tenant_group_id'] ?? null)) {
            return null;
        }

        $value = (int) $match['tenant_group_id'];

        return $value > 0 ? $value : null;
    }

    public function bumpSelectorVersionForTenant(int $tenantGroupId): int
    {
        return $this->cacheVersions->bump("tenant:{$tenantGroupId}:".self::NS_SELECTORS_COMPANIES);
    }

    public function canAccessCompanySelection(User $user): bool
    {
        return $user->hasRole('superadmin') || $user->can(CompanyPolicy::PERM_VIEW);
    }

    private function currentTenantGroupId(): ?int
    {
        $tenantId = TenantGroup::current()?->id;

        if (! is_numeric($tenantId)) {
            return null;
        }

        $value = (int) $tenantId;

        return $value > 0 ? $value : null;
    }
}
