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
    /**
     * @param int $id Cég azonosító
     * @param string $name Cég név
     * @param string $email Cég e-mail cím
     * @param ?string $address Cég címe
     * @param ?string $phone Cég telefonszáma
     * @param bool $active Aktív státusz
     * @param ?string $createdAt Létrehozás ideje
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public ?string $address,
        public ?string $phone,
        public bool $active,
        public ?string $createdAt,
    ) {}

    /**
     * DTO előállítása modelből.
     *
     * @param Company $company Cég model
     * @return self
     */
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
