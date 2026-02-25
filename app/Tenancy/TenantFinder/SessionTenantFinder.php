<?php

declare(strict_types=1);

namespace App\Tenancy\TenantFinder;

use App\Models\TenantGroup;
use Illuminate\Http\Request;
use Spatie\Multitenancy\Contracts\IsTenant;
use Spatie\Multitenancy\TenantFinder\TenantFinder;

class SessionTenantFinder extends TenantFinder
{
    public function findForRequest(Request $request): ?IsTenant
    {
        if (! $request->hasSession()) {
            return null;
        }

        $tenantGroupId = $request->session()->get('current_tenant_group_id');
        if (! is_numeric($tenantGroupId)) {
            return null;
        }

        return TenantGroup::query()
            ->whereKey((int) $tenantGroupId)
            ->where('active', true)
            ->first();
    }
}
