<?php

declare(strict_types=1);

use App\Models\Company;
use App\Services\Tenant\TenantManager;

if (! function_exists('resolveTenantScopedCompany')) {
    function resolveTenantScopedCompany(int $companyId): Company
    {
        /** @var TenantManager $tenantManager */
        $tenantManager = app(TenantManager::class);

        return $tenantManager->resolveTenantScopedCompany($companyId);
    }
}
