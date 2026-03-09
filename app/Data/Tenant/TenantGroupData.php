<?php

declare(strict_types=1);

namespace App\Data\Tenant;

use App\Models\TenantGroup;
use Spatie\LaravelData\Data;

/**
 * Teljes landlord DTO a TenantGroup létrehozási, frissítési és részletező folyamataihoz.
 */
final class TenantGroupData extends Data
{
    public function __construct(
        public ?int $id,
        public string $name,
        public string $code,
        public ?string $status,
        public bool $active,
        public ?string $notes,
        public ?string $databaseName,
        public ?string $createdAt,
        public ?string $updatedAt,
        public ?string $deletedAt,
    ) {}

    /**
     * Az Eloquent modellből DTO-t épít, hogy a controller és a service ugyanazt a szerződést használja.
     */
    public static function fromModel(TenantGroup $tenantGroup): self
    {
        return new self(
            id: (int) $tenantGroup->id,
            name: (string) $tenantGroup->name,
            code: (string) $tenantGroup->code,
            status: $tenantGroup->status !== null ? (string) $tenantGroup->status : null,
            active: (bool) $tenantGroup->active,
            notes: $tenantGroup->notes !== null ? (string) $tenantGroup->notes : null,
            databaseName: $tenantGroup->database_name !== null ? (string) $tenantGroup->database_name : null,
            createdAt: $tenantGroup->created_at?->toDateTimeString(),
            updatedAt: $tenantGroup->updated_at?->toDateTimeString(),
            deletedAt: $tenantGroup->deleted_at?->toDateTimeString(),
        );
    }
}
