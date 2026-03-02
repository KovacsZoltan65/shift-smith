<?php

namespace App\Data\Employee;

use App\Models\Employee;
use Spatie\LaravelData\Data;

/**
 * Minimal munkavállaló DTO táblázatos listázáshoz.
 */
class EmployeeIndexData extends Data
{
    /**
     * @param ?int $id Munkavállaló azonosító
     * @param int $company_id Cég azonosító
     * @param string $first_name Keresztnév
     * @param string $last_name Vezetéknév
     * @param string $email E-mail cím
     * @param string $address Cím
     * @param ?int $position_id Pozíció azonosító
     * @param ?string $position_name Pozíció
     * @param string $phone Telefonszám
     * @param ?string $birth_date Születési dátum
     * @param string $hired_at Belépés dátuma
     * @param bool $active Aktív státusz
     */
    public function __construct(
        public ?int $id,
        public int $company_id,
        public string $first_name,
        public string $last_name,
        public string $email,
        public string $address,
        public ?int $position_id,
        public ?string $position_name,
        public ?string $phone,
        public ?string $birth_date,
        public ?string $hired_at,
        public bool $active,

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
            id: (int) $employee->id,
            company_id: (int) $employee->company_id,
            first_name: (string) $employee->first_name,
            last_name: (string) $employee->last_name,
            email: (string) $employee->email,
            address: (string) $employee->address,
            position_id: $employee->position_id ? (int) $employee->position_id : null,
            position_name: $employee->position?->name,
            phone: $employee->phone,
            birth_date: optional($employee->birth_date)?->format('Y-m-d'),
            hired_at: optional($employee->hired_at)?->format('Y-m-d'),
            active: (bool) $employee->active,
        );
    }
}
