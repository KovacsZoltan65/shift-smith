<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Tenant\TenantManager;
use RuntimeException;

final class TenantContext
{
    public function __construct(
        private readonly TenantManager $tenantManager,
    ) {}

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
        return $this->tenantManager->tenantIdOrNull();
    }
}
