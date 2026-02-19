<?php

declare(strict_types=1);

namespace App\Interfaces;

use App\Models\EmployeeWorkPattern;

interface EmployeeWorkPatternRepositoryInterface
{
    /**
     * @param int $employeeId
     * @param int $companyId
     * @return list<EmployeeWorkPattern>
     */
    public function listByEmployee(int $employeeId, int $companyId): array;

    /**
     * @param array{
     *   company_id:int,
     *   employee_id:int,
     *   work_pattern_id:int,
     *   date_from:string,
     *   date_to?:string|null,
     *   is_primary?:bool,
     *   meta?:array<string,mixed>|null
     * } $data
     */
    public function assign(array $data): EmployeeWorkPattern;

    /**
     * @param int $id
     * @param int $employeeId
     * @param array{
     *   work_pattern_id:int,
     *   date_from:string,
     *   date_to?:string|null,
     *   is_primary?:bool,
     *   meta?:array<string,mixed>|null
     * } $data
     */
    public function updateAssignment(int $id, int $employeeId, array $data): EmployeeWorkPattern;

    public function unassign(int $id, int $employeeId): bool;
}
