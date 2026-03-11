<?php

declare(strict_types=1);

namespace App\Data\Company;

use App\Models\Company;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;

final class HqCompanyData extends Data
{
    public function __construct(
        public ?int $id,
        public int $tenantGroupId,
        public string $name,
        public ?string $email,
        public ?string $address,
        public ?string $phone,
        public bool $active = true,
        public ?string $tenantGroupCode = null,
        public ?string $tenantGroupName = null,
        #[MapName('created_at')]
        public ?string $createdAt = null,
    ) {}

    public static function fromModel(Company $company): self
    {
        $company->loadMissing('tenantGroup:id,name,code');

        return new self(
            id: (int) $company->id,
            tenantGroupId: (int) $company->tenant_group_id,
            name: (string) $company->name,
            email: $company->email !== null ? (string) $company->email : null,
            address: $company->address !== null ? (string) $company->address : null,
            phone: $company->phone !== null ? (string) $company->phone : null,
            active: (bool) $company->active,
            tenantGroupCode: $company->tenantGroup?->code,
            tenantGroupName: $company->tenantGroup?->name,
            createdAt: optional($company->created_at)?->toDateTimeString(),
        );
    }
}
