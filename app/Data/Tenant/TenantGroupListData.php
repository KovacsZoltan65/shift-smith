<?php

declare(strict_types=1);

namespace App\Data\Tenant;

use App\Models\TenantGroup;
use Spatie\LaravelData\Data;

/**
 * Könnyített DTO a HQ TenantGroup lista- és datatable végpontokhoz.
 */
final class TenantGroupListData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $code,
        public ?string $status,
        public bool $active,
        public ?string $createdAt,
    ) {}

    public static function fromModel(TenantGroup $tenantGroup): self
    {
        return new self(
            id: (int) $tenantGroup->id,
            name: (string) $tenantGroup->name,
            code: (string) $tenantGroup->code,
            status: $tenantGroup->status !== null ? (string) $tenantGroup->status : null,
            active: (bool) $tenantGroup->active,
            createdAt: $tenantGroup->created_at?->toDateTimeString(),
        );
    }
}
