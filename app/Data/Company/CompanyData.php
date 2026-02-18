<?php

namespace App\Data\Company;

use App\Models\Company;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Attributes\Validation\Unique;
use Spatie\LaravelData\Support\Validation\References\RouteParameterReference;

class CompanyData extends Data
{
    /**
     * Create a new class instance.
     */
    public function __construct(
        public ?int $id,

        #[Required, StringType, Max(150)]
        public string $name,

        #[Required, Email, Max(150), Unique('companies', 'email', new RouteParameterReference('id'))]
        public string $email,

        #[Nullable, StringType, max(255)]
        public ?string $address,

        #[StringType, Max(50)]
        public ?string $phone,

        public bool $active,

        #[MapName('created_at')]
        public ?string $createdAt,
    ) {}

    public static function fromModel(Company $company): self
    {
        return new self(
            id: $company->id,
            name: $company->name,
            email: $company->email,
            address: $company->address,
            phone: $company->phone,
            active: $company->active,
            createdAt: optional($company->created_at)?->toDateTimeString(),
        );
    }
}
