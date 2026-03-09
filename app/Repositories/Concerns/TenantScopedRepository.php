<?php

declare(strict_types=1);

namespace App\Repositories\Concerns;

use App\Models\Company;
use App\Services\Tenant\TenantManager;
use Illuminate\Database\Eloquent\Builder;

/**
 * Közös segédfüggvények azokhoz a repositorykhoz, amelyeknek TenantGroup és Company izolációt kell tartaniuk.
 */
trait TenantScopedRepository
{
    protected function tenantManager(): TenantManager
    {
        /** @var TenantManager $manager */
        $manager = app(TenantManager::class);

        return $manager;
    }

    protected function ensureTenantContext(): void
    {
        $this->tenantManager()->ensureTenantContext();
    }

    protected function tenantId(): int
    {
        return $this->tenantManager()->tenantId();
    }

    /**
     * Tenant-scoped query kiindulópont azokhoz a modellekhez, amelyek közvetlenül tárolják a tenant_group_id mezőt.
     *
     * @param class-string<\Illuminate\Database\Eloquent\Model> $modelClass
     */
    protected function tenantQuery(string $modelClass, string $tenantColumn = 'tenant_group_id'): Builder
    {
        return $modelClass::query()->where($tenantColumn, $this->tenantId());
    }

    /**
     * Company-scoped query kiindulópont, miután ellenőriztük, hogy a company az aktív tenanthoz tartozik.
     *
     * @param class-string<\Illuminate\Database\Eloquent\Model> $modelClass
     */
    protected function companyQuery(string $modelClass, int $companyId, string $companyColumn = 'company_id'): Builder
    {
        return $modelClass::query()->where($companyColumn, $this->resolveTenantScopedCompanyId($companyId));
    }

    protected function resolveTenantScopedCompany(int $companyId): Company
    {
        return $this->tenantManager()->resolveTenantScopedCompany($companyId);
    }

    protected function resolveTenantScopedCompanyId(int $companyId): int
    {
        return $this->tenantManager()->resolveTenantScopedCompanyId($companyId);
    }
}
