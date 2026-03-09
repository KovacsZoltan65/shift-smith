<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use RuntimeException;

/**
 * Akkor dobódik, ha a landlord oldali tenant archiválását kapcsolódó company vagy domain adatok blokkolják.
 */
final class TenantGroupDeletionBlockedException extends RuntimeException
{
    /**
     * @param array<string,int> $impact
     */
    public function __construct(
        public readonly array $impact,
        string $message = 'tenant_groups.delete_blocked_companies',
    ) {
        parent::__construct(__($message));
    }
}
