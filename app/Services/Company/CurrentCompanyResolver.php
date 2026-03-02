<?php

declare(strict_types=1);

namespace App\Services\Company;

use App\Interfaces\CompanyRepositoryInterface;
use App\Services\CurrentCompany;
use App\Services\CurrentTenantGroup;
use App\Models\TenantGroup;
use Illuminate\Http\Request;
use Illuminate\Session\Store;

final class CurrentCompanyResolver
{
    public function __construct(
        private readonly CompanyRepositoryInterface $companies,
        private readonly CurrentCompany $currentCompany,
        private readonly CurrentTenantGroup $currentTenantGroup,
    ) {
    }

    public function resolveCompanyId(): ?int
    {
        $request = $this->request();

        if ($request instanceof Request && $request->hasSession()) {
            $companyId = $this->currentCompany->currentCompanyId($request);
            $tenantGroupId = $this->currentTenantGroup->currentTenantGroupId($request);

            if ($this->isValidCompanyContext($companyId, $tenantGroupId)) {
                return $companyId;
            }

            if ($companyId !== null) {
                $request->session()->forget([
                    CurrentCompany::SESSION_KEY,
                    CurrentTenantGroup::SESSION_KEY,
                ]);
            }

            return null;
        }

        $companyId = $this->sessionValue(CurrentCompany::SESSION_KEY);
        $tenantGroupId = $this->sessionValue(CurrentTenantGroup::SESSION_KEY);

        if ($this->isValidCompanyContext($companyId, $tenantGroupId)) {
            return $companyId;
        }

        if ($companyId !== null) {
            $this->forgetSessionContext();
        }

        return null;
    }

    public function resolveTenantGroupId(): ?int
    {
        $request = $this->request();

        if ($request instanceof Request && $request->hasSession()) {
            $tenantGroupId = $this->currentTenantGroup->currentTenantGroupId($request);
            if ($tenantGroupId !== null) {
                return $tenantGroupId;
            }
        }

        $sessionTenantGroupId = $this->sessionValue(CurrentTenantGroup::SESSION_KEY);
        if ($sessionTenantGroupId !== null) {
            return $sessionTenantGroupId;
        }

        $currentTenantId = TenantGroup::current()?->id;

        if (! is_numeric($currentTenantId)) {
            return null;
        }

        $tenantGroupId = (int) $currentTenantId;

        return $tenantGroupId > 0 ? $tenantGroupId : null;
    }

    private function request(): ?Request
    {
        $request = request();

        return $request instanceof Request ? $request : null;
    }

    private function sessionValue(string $key): ?int
    {
        $store = app()->bound('session.store') ? app('session.store') : null;

        if (! $store instanceof Store) {
            return null;
        }

        $value = $store->get($key);

        if (! is_numeric($value)) {
            return null;
        }

        $id = (int) $value;

        return $id > 0 ? $id : null;
    }

    private function isValidCompanyContext(?int $companyId, ?int $tenantGroupId): bool
    {
        if ($companyId === null || $tenantGroupId === null) {
            return false;
        }

        return $this->companies->companyBelongsToActiveTenantGroup($companyId, $tenantGroupId);
    }

    private function forgetSessionContext(): void
    {
        $store = app()->bound('session.store') ? app('session.store') : null;

        if (! $store instanceof Store) {
            return;
        }

        $store->forget([
            CurrentCompany::SESSION_KEY,
            CurrentTenantGroup::SESSION_KEY,
        ]);
    }
}
