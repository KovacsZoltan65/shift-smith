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
     * } $data
     */
    public function assign(array $data): EmployeeWorkPattern;

    /**
     * @param int $id
     * @param int $employeeId
     * @param int $companyId
     * @param array{
     *   work_pattern_id:int,
     *   date_from:string,
     *   date_to?:string|null
     * } $data
     */
    public function updateAssignment(int $id, int $employeeId, int $companyId, array $data): EmployeeWorkPattern;

    public function unassign(int $id, int $employeeId, int $companyId): bool;

    public function hasOverlap(
        int $companyId,
        int $employeeId,
        string $dateFrom,
        ?string $dateTo,
        ?int $ignoreId = null
    ): bool;

    public function findActiveForEmployeeOnDate(int $companyId, int $employeeId, string $date): ?EmployeeWorkPattern;

    public function findNextForEmployeeAfterDate(int $companyId, int $employeeId, string $date): ?EmployeeWorkPattern;

    public function closeAssignment(int $id, int $companyId, string $dateTo): EmployeeWorkPattern;

    public function createAssignment(
        int $companyId,
        int $employeeId,
        int $workPatternId,
        string $dateFrom,
        ?string $dateTo = null
    ): EmployeeWorkPattern;
}
