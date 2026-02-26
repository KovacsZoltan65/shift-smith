<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Company;
use App\Models\TenantGroup;
use App\Models\User;
use App\Services\CompanyContextService;
use App\Services\CurrentCompany;
use App\Services\CurrentTenantGroup;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureTenantGroupContext
{
    public function __construct(
        private readonly CurrentCompany $currentCompany,
        private readonly CurrentTenantGroup $currentTenantGroup,
        private readonly CompanyContextService $companyContext,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $selectedCompanyId = $this->currentCompany->currentCompanyId($request);
        abort_if($selectedCompanyId === null, 403, 'No company selected');

        $company = Company::query()
            ->whereKey($selectedCompanyId)
            ->where('active', true)
            ->firstOrFail(['id', 'tenant_group_id']);

        $tenantGroupId = (int) $company->tenant_group_id;
        abort_if($tenantGroupId <= 0, 403, 'Selected company has invalid tenant group');

        $sessionTenantGroupId = $this->currentTenantGroup->currentTenantGroupId($request);
        if ($sessionTenantGroupId !== null && $sessionTenantGroupId !== $tenantGroupId) {
            abort(403, 'Tenant context mismatch');
        }

        $currentTenantGroup = TenantGroup::current();
        if ($currentTenantGroup !== null) {
            if ((int) $currentTenantGroup->id !== $tenantGroupId) {
                abort(403, 'Tenant context mismatch');
            }

            return $next($request);
        }

        $user = $request->user('web');
        if ($user instanceof User && ! $this->companyContext->isSuperadmin($user)) {
            $canSelect = $user->companies()->whereKey($selectedCompanyId)->exists();
            abort_unless($canSelect, 403, 'The selected company is not assigned to the current user.');
        }

        $tenantGroup = TenantGroup::query()
            ->whereKey($tenantGroupId)
            ->where('active', true)
            ->firstOrFail();

        $tenantGroup->makeCurrent();
        $this->currentTenantGroup->setCurrentTenantGroupId($request, $tenantGroupId);

        return $next($request);
    }
}
