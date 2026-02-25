<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\TenantGroup;
use RuntimeException;

final class TenantContext
{
    public function currentTenantGroupIdOrFail(): int
    {
        $tenantGroupId = $this->currentTenantGroupIdOrNull();

        if ($tenantGroupId === null) {
            throw new RuntimeException('Tenant group context is missing.');
        }

        return $tenantGroupId;
    }

    public function currentTenantGroupIdOrNull(): ?int
    {
        $tenantGroupId = TenantGroup::current()?->id;

        if (! is_int($tenantGroupId) || $tenantGroupId <= 0) {
            return null;
        }

        return $tenantGroupId;
    }
}

