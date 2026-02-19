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
     * @param string $position Pozíció
     * @param string $phone Telefonszám
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
        public string $position,
        public string $phone,
        public string $hired_at,
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
        return new self(
            id: (int) $employee->id,
            company_id: (int) $employee->company_id,
            first_name: (string) $employee->first_name,
            last_name: (string) $employee->last_name,
            email: (string) $employee->email,
            address: (string) $employee->address,
            position: (string) $employee->position,
            phone: $employee->phone,
            hired_at: $employee->hired_at,
            active: (bool) $employee->active,
        );
    }
}
