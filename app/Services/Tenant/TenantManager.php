<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Models\Company;
use App\Models\TenantGroup;
use App\Services\CurrentCompany;
use App\Services\CurrentTenantGroup;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Központi tenant kontextus feloldó a request-alapú landlord és tenant folyamatokhoz.
 *
 * Az aktív tenant minden esetben egy TenantGroup. A company feloldása csak ezután
 * történhet, és mindig a már meghatározott tenant kontextushoz kell kötődnie.
 */
final class TenantManager
{
    public function __construct(
        private readonly CurrentCompany $currentCompany,
        private readonly CurrentTenantGroup $currentTenantGroup,
    ) {}

    /**
     * Feloldja az aktív tenantot, vagy hibát dob, ha a kérésből hiányzik a tenant kontextus.
     */
    public function tenant(?Request $request = null): TenantGroup
    {
        $tenant = $this->resolveCurrentTenant($request);

        abort_if($tenant === null, Response::HTTP_UNPROCESSABLE_ENTITY, __('tenant.errors.missing_tenant_context'));

        return $tenant;
    }

    public function tenantOrNull(?Request $request = null): ?TenantGroup
    {
        return $this->resolveCurrentTenant($request);
    }

    /**
     * Visszaadja az aktuális TenantGroup azonosítóját a tenant-scoped service és repository réteg számára.
     */
    public function tenantId(?Request $request = null): int
    {
        return (int) $this->tenant($request)->id;
    }

    public function tenantIdOrNull(?Request $request = null): ?int
    {
        $tenant = $this->tenantOrNull($request);

        return $tenant instanceof TenantGroup ? (int) $tenant->id : null;
    }

    public function companyId(?Request $request = null): int
    {
        $companyId = $this->companyIdOrNull($request);

        abort_if($companyId === null, Response::HTTP_UNPROCESSABLE_ENTITY, __('tenant.errors.missing_company_context'));

        return $companyId;
    }

    /**
     * Az aktuális company azonosítót csak akkor adja vissza, ha az a feloldott tenanthoz tartozik.
     */
    public function companyIdOrNull(?Request $request = null): ?int
    {
        $request ??= $this->request();

        if (! $request instanceof Request || ! $request->hasSession()) {
            return null;
        }

        $companyId = $this->currentCompany->currentCompanyId($request);
        if ($companyId === null) {
            return null;
        }

        return (int) $this->resolveTenantScopedCompany($companyId, $request)->id;
    }

    /**
     * Betölti a request attribútumokat, amelyekre a tenant-aware rétegek támaszkodnak.
     */
    public function ensureTenantContext(?Request $request = null): void
    {
        $request ??= $this->request();
        $tenant = $this->tenant($request);
        $this->injectContext($request, $tenant);
    }

    /**
     * Felold egy company rekordot az aktív tenanton belül.
     *
     * Ezt a védelmet azok a repositoryk és service-ek használják, amelyek company azonosítót
     * kapnak a request rétegből, és még bármilyen domain lekérdezés előtt meg kell akadályozniuk
     * a cross-tenant hozzáférést.
     */
    public function resolveTenantScopedCompany(int $companyId, ?Request $request = null): Company
    {
        abort_if($companyId <= 0, Response::HTTP_UNPROCESSABLE_ENTITY, __('tenant.errors.missing_company_context'));

        $tenant = $this->tenant($request);

        /** @var Company $company */
        $company = Company::query()
            ->whereKey($companyId)
            ->where('tenant_group_id', (int) $tenant->id)
            ->where('active', true)
            ->firstOrFail();

        $this->injectContext($request, $tenant, (int) $company->id);

        return $company;
    }

    public function resolveTenantScopedCompanyId(int $companyId, ?Request $request = null): int
    {
        return (int) $this->resolveTenantScopedCompany($companyId, $request)->id;
    }

    private function resolveCurrentTenant(?Request $request = null): ?TenantGroup
    {
        $request ??= $this->request();
        $sessionTenantId = null;

        if ($request instanceof Request && $request->hasSession()) {
            $sessionTenantId = $this->currentTenantGroup->currentTenantGroupId($request);
        }

        $currentTenant = TenantGroup::current();
        if ($sessionTenantId !== null) {
            if ($currentTenant?->id === $sessionTenantId) {
                return $currentTenant;
            }

            // A sessionben tárolt tenant azonosítóból újraépítjük a Spatie current tenant
            // állapotot, hogy a HTTP request és a későbbi multi-database működés ugyanarra
            // a kontextusforrásra támaszkodjon.
            /** @var TenantGroup|null $tenant */
            $tenant = TenantGroup::query()
                ->whereKey($sessionTenantId)
                ->where('active', true)
                ->first();

            if ($tenant === null) {
                TenantGroup::forgetCurrent();

                return null;
            }

            $tenant->makeCurrent();

            return $tenant;
        }

        if ($currentTenant instanceof TenantGroup && (bool) $currentTenant->active) {
            return $currentTenant;
        }

        return null;
    }

    private function injectContext(?Request $request, TenantGroup $tenant, ?int $companyId = null): void
    {
        if (! $request instanceof Request) {
            return;
        }

        $request->attributes->set('tenant_group_id', (int) $tenant->id);
        $request->attributes->set('tenant_group', $tenant);

        if ($companyId !== null) {
            $request->attributes->set('company_id', $companyId);
        }
    }

    private function request(): ?Request
    {
        $request = request();

        return $request instanceof Request ? $request : null;
    }
}
