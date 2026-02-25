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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
        $sessionTenantId = $this->currentTenantGroup->currentTenantGroupId($request);

        if ($currentCompanyId !== null) {
            if ($sessionTenantId !== null) {
                if (! $this->isCurrentCompanyValidForTenant($user, $currentCompanyId, $sessionTenantId)) {
                    return $this->resetAndRedirect($request);
                }

                return $next($request);
            }

            if ($this->companyContext->userCanSelectCompany($user, $currentCompanyId)) {
                // IDE:
                $tenantGroupId = $this->companyContext->tenantGroupIdForCompany($user, $currentCompanyId);

                if ($tenantGroupId !== null) {
                    $this->currentTenantGroup->setCurrentTenantGroupId($request, $tenantGroupId);
                } else {
                    Log::error('company.missing_tenant_group_id', [
                        'company_id' => $currentCompanyId,
                        'user_id' => $user->id,
                    ]);

                    return $this->resetAndRedirect($request);
                }

                return $next($request);
            }

            return $this->resetAndRedirect($request);
        }

        $companyCount = $this->companyContext->countSelectableCompanies($user);

        if ($companyCount === 1) {
            $companyId = $this->companyContext->firstSelectableCompanyId($user);

            if ($companyId !== null) {
                $this->currentCompany->setCurrentCompanyId($request, $companyId);

                $tenantGroupId = $this->companyContext->tenantGroupIdForCompany($user, $companyId);
                if ($tenantGroupId !== null) {
                    $this->currentTenantGroup->setCurrentTenantGroupId($request, $tenantGroupId);
                } else {
                    Log::error('company.missing_tenant_group_id', [
                        'company_id' => $companyId,
                        'user_id' => $user->id,
                    ]);

                    return $this->resetAndRedirect($request);
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

    private function resetAndRedirect(Request $request): RedirectResponse
    {
        $this->currentCompany->clearCurrentCompany($request);
        $this->currentTenantGroup->clearCurrentTenantGroup($request);

        return redirect()->route('company.select');
    }
}
