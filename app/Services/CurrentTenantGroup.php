<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

final class CurrentTenantGroup
{
    public const SESSION_KEY = 'current_tenant_group_id';

    public function currentTenantGroupId(Request $request): ?int
    {
        $value = $request->session()->get(self::SESSION_KEY);

        if (! is_numeric($value)) {
            return null;
        }

        $id = (int) $value;

        return $id > 0 ? $id : null;
    }

    public function setCurrentTenantGroupId(Request $request, int $tenantGroupId): void
    {
        if ($tenantGroupId <= 0) {
            $request->session()->forget(CurrentTenantGroup::SESSION_KEY);

            Log::warning('tenant_group.invalid_id', [
                'tenant_group_id' => $tenantGroupId,
            ]);

            return;
        }

        $request->session()->put(self::SESSION_KEY, $tenantGroupId);
    }

    public function clearCurrentTenantGroup(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
    }
}
