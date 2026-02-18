<?php

declare(strict_types=1);

namespace App\Data\Company;

use App\Models\Company;
use Spatie\LaravelData\Data;

/**
 * Minimal Company DTO for table listings.
 */
class CompanyIndexData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public ?string $address,
        public ?string $phone,
        public bool $active,
        public ?string $createdAt,
    ) {}

    public static function fromModel(Company $company): self
    {
        return new self(
            id: (int) $company->id,
            name: (string) $company->name,
            email: (string) $company->email,
            address: (string) $company->address,
            phone: $company->phone,
            active: (bool) $company->active,
            createdAt: optional($company->created_at)?->toDateTimeString(),
        );
    }
}
