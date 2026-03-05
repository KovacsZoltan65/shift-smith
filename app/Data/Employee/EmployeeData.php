<?php

declare(strict_types=1);

namespace App\Data\Employee;

use App\Models\Employee;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\DateFormat;
use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Attributes\Validation\BooleanType;
use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Attributes\Validation\Unique;
use Spatie\LaravelData\Support\Validation\References\RouteParameterReference;

/**
 * Teljes munkavállaló DTO create/update és részletes megjelenítés célra.
 */
class EmployeeData extends Data
{
    /**
     * @param ?int $id Munkavállaló azonosító
     * @param ?int $company_id Cég azonosító
     * @param string $first_name Keresztnév
     * @param string $last_name Vezetéknév
     * @param string $email E-mail cím
     * @param string $address Cím
     * @param ?int $position_id Pozíció azonosító
     * @param string $org_level Szervezeti szint
     * @param ?string $phone Telefonszám
     * @param ?string $hired_at Belépés dátuma (Y-m-d)
     * @param bool $active Aktív státusz
     * @param ?string $position_name Pozíció név
     */
    public function __construct(
        public ?int $id,

        #[Required]
        public int $company_id,

        #[Required, StringType]
        public string $first_name,

        #[Required, StringType]
        public string $last_name,

        #[Required, Email, Max(120), Unique('employees', 'email', null, new RouteParameterReference('id', null, true))]
        public string $email,

        #[Required, DateFormat('Y-m-d')]
        public string $birth_date,

        #[Nullable, StringType, Max(255)]
        public ?string $address = null,

        #[Nullable, IntegerType, Exists('positions', 'id')]
        public ?int $position_id = null,

        #[StringType, In(Employee::ORG_LEVELS)]
        public string $org_level = Employee::ORG_LEVEL_STAFF,

        #[Nullable, StringType, Max(50)]
        public ?string $phone = null,

        #[Nullable, DateFormat('Y-m-d')]
        public ?string $hired_at = null,

        #[BooleanType]
        public bool $active = true,

        public ?string $position_name = null,

    ) {}

    /**
     * DTO előállítása modelből.
     *
     * @param Employee $employee Munkavállaló model
     * @return self
     */
    public static function fromModel(Employee $employee): self
    {
        $employee->loadMissing('position:id,name');

        return new self(
            id: $employee->id,
            company_id: $employee->company_id,
            first_name: $employee->first_name,
            last_name: $employee->last_name,
            email: $employee->email,
            birth_date: optional($employee->birth_date)?->format('Y-m-d') ?? '',
            address: $employee->address,
            position_id: $employee->position_id,
            org_level: (string) $employee->org_level,
            phone: $employee->phone,
            hired_at: optional($employee->hired_at)?->format('Y-m-d'),
            active: $employee->active,
            position_name: $employee->position?->name,
        );
    }
}
