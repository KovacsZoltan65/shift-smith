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

/**
 * Biztosítja, hogy a tenant oldali route-ok csak érvényes company + tenant kontextussal fussanak.
 *
 * A middleware tenant izolációs védőréteg: a sessionben tárolt company azonosítót mindig
 * TenantGroup kontextussal együtt validálja, mielőtt a kérés elérné a controller réteget.
 */
final class EnsureCompanySelected
{
    public function __construct(
        private readonly CurrentCompany $currentCompany,
        private readonly CompanyContextService $companyContext,
        private readonly CurrentTenantGroup $currentTenantGroup,
    ) {}

    /**
     * Szinkronban tartja a session company kiválasztást és a Spatie current tenant állapotot.
     */
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
                if (! $this->syncCurrentTenantGroup($sessionTenantId)) {
                    return $this->resetAndRedirect($request);
                }

                if (! $this->isCurrentCompanyValidForTenant($user, $currentCompanyId, $sessionTenantId)) {
                    return $this->resetAndRedirect($request);
                }

                return $next($request);
            }

            if ($this->companyContext->userCanSelectCompany($user, $currentCompanyId)) {
                // A company kiválasztás csak akkor fogadható el, ha a hozzá tartozó TenantGroup is feloldható.
                $tenantGroupId = $this->companyContext->tenantGroupIdForCompany($user, $currentCompanyId);

                if ($tenantGroupId !== null) {
                    $this->currentTenantGroup->setCurrentTenantGroupId($request, $tenantGroupId);
                    if (! $this->syncCurrentTenantGroup($tenantGroupId)) {
                        return $this->resetAndRedirect($request);
                    }
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
                    if (! $this->syncCurrentTenantGroup($tenantGroupId)) {
                        return $this->resetAndRedirect($request);
                    }
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

        abort(403, __('common.errors.no_company_assigned'));
    }

    /**
     * Ellenőrzi, hogy a sessionben tárolt company valóban az aktív TenantGroup része-e.
     */
    private function isCurrentCompanyValidForTenant(User $user, int $companyId, int $tenantId): bool
    {
        $query = Company::query()
            ->whereKey($companyId)
            ->where('tenant_group_id', $tenantId)
            ->where('active', true);

        if (! $query->exists()) {
            return false;
        }

        if ($this->companyContext->isSuperadmin($user)) {
            return true;
        }

        return $this->companyContext->userCanSelectCompany($user, $companyId);
    }

    private function isHqRoute(Request $request): bool
    {
        $routeName = $request->route()?->getName();

        return is_string($routeName) && str_starts_with($routeName, 'hq.');
    }

    /**
     * A sessionből érkező tenant azonosítót összerendezi a Spatie current tenant állapottal.
     */
    private function syncCurrentTenantGroup(int $tenantGroupId): bool
    {
        $currentTenant = TenantGroup::current();
        if ($currentTenant?->id === $tenantGroupId) {
            return true;
        }

        $tenant = TenantGroup::query()
            ->whereKey($tenantGroupId)
            ->where('active', true)
            ->first();

        if ($tenant === null) {
            Log::warning('tenant_group.session_tenant_not_found', [
                'tenant_group_id' => $tenantGroupId,
            ]);

            TenantGroup::forgetCurrent();

            return false;
        }

        $tenant->makeCurrent();

        return true;
    }

    /**
     * Törli az inkonzisztens company és tenant session állapotot, majd visszairányít a választó oldalra.
     */
    private function resetAndRedirect(Request $request): RedirectResponse
    {
        $this->currentCompany->clearCurrentCompany($request);
        $this->currentTenantGroup->clearCurrentTenantGroup($request);

        return redirect()->route('company.select');
    }
}
