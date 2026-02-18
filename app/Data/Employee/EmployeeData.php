<?php

namespace App\Data\Employee;

use App\Models\Employee;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Attributes\Validation\BooleanType;
use Spatie\LaravelData\Attributes\Validation\Unique;
use Spatie\LaravelData\Support\Validation\References\RouteParameterReference;

class EmployeeData extends Data
{
    public function __construct(
        public ?int $id,

        #[Required]
        public ?int $company_id,

        #[Required, StringType]
        public string $first_name,

        #[Required, StringType]
        public string $last_name,

        #[Required, Email, Max(150), Unique('employees', 'email', new RouteParameterReference('id'))]
        public string $email,

        #[Required, StringType]
        public string $address,

        #[Required, StringType]
        public string $position,

        #[Required, StringType]
        public string $phone,

        #[Required, StringType]
        public string $hired_at,

        #[Required, BooleanType]
        public bool $active,

    ) {}

    public static function fromModel(Employee $employee): self
    {
        return new self(
            id: $employee->id,
            company_id: $employee->company_id,
            first_name: $employee->first_name,
            last_name: $employee->last_name,
            email: $employee->email,
            address: $employee->address,
            position: $employee->position,
            phone: $employee->phone,
            hired_at: $employee->hired_at,
            active: $employee->active,
        );
    }
}

