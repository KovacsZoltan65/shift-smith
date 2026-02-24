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

final class EnsureCompanySelected
{
    public function __construct(
        private readonly CurrentCompany $currentCompany,
        private readonly CompanyContextService $companyContext,
        private readonly CurrentTenantGroup $currentTenantGroup,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('web');

        if (!$user instanceof User) {
            return $next($request);
        }

        if ($this->isHqRoute($request)) {
            return $next($request);
        }

        $currentCompanyId = $this->currentCompany->currentCompanyId($request);
        $currentTenantId = TenantGroup::current()?->id;
        $sessionTenantId = $this->currentTenantGroup->currentTenantGroupId($request);
        $tenantIdForValidation = $currentTenantId ?? $sessionTenantId;

        if ($currentCompanyId !== null) {
            if ($tenantIdForValidation !== null) {
                if (! $this->isCurrentCompanyValidForTenant($user, $currentCompanyId, $tenantIdForValidation)) {
                    $this->currentCompany->clearCurrentCompany($request);
                    $this->currentTenantGroup->clearCurrentTenantGroup($request);
                    return redirect()->route('company.select');
                }

                return $next($request);
            }

            if ($this->companyContext->userCanSelectCompany($user, $currentCompanyId)) {
                // IDE:
                $tenantGroupId = $this->companyContext->tenantGroupIdForCompany($user, $currentCompanyId);

                if ($tenantGroupId !== null) {
                    $this->currentTenantGroup->setCurrentTenantGroupId($request, $tenantGroupId);
                } else {
                    // ha valamiért nincs tenant_group_id a céghez, inkább töröljük, hogy ne legyen fals state
                    $this->currentCompany->clearCurrentCompany($request);
                    $this->currentTenantGroup->clearCurrentTenantGroup($request);
                    return redirect()->route('company.select');
                }

                return $next($request);
            }

            $this->currentCompany->clearCurrentCompany($request);
            $this->currentTenantGroup->clearCurrentTenantGroup($request);
            return redirect()->route('company.select');
        }

        $companyCount = $this->companyContext->countSelectableCompanies($user);

        if ($companyCount === 1) {
            $companyId = $this->companyContext->firstSelectableCompanyId($user);

            if ($companyId !== null) {
                $this->currentCompany->setCurrentCompanyId($request, $companyId);

                $tenantGroupId = $this->companyContext->tenantGroupIdForCompany($user, $companyId);
                if ($tenantGroupId !== null) {
                    $this->currentTenantGroup->setCurrentTenantGroupId($request, $tenantGroupId);
                }

                return $next($request);
            }
        }

        if ($companyCount > 1) {
            return redirect()->route('company.select');
        }

        if ($this->companyContext->isSuperadmin($user)) {
            return $next($request);
        }

        abort(403, 'No company assigned');
    }

    private function isCurrentCompanyValidForTenant(User $user, int $companyId, int $tenantId): bool
    {
        $query = Company::query()
            ->whereKey($companyId)
            ->where('tenant_group_id', $tenantId)
            ->where('active', true);

        if (! $this->companyContext->isSuperadmin($user)) {
            $query->whereHas('users', fn ($q) => $q->whereKey($user->id));
        }

        return $query->exists();
    }

    private function isHqRoute(Request $request): bool
    {
        $routeName = $request->route()?->getName();

        return is_string($routeName) && str_starts_with($routeName, 'hq.');
    }
}
