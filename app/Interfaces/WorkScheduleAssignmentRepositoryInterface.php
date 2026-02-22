<?php

declare(strict_types=1);

namespace App\Interfaces;

use App\Models\WorkShiftAssignment;
use Illuminate\Support\Collection;

interface WorkScheduleAssignmentRepositoryInterface
{
    /**
     * @param array{
     *   start?: string|null,
     *   end?: string|null,
     *   employee_ids?: array<int,int>,
     *   work_shift_ids?: array<int,int>,
     *   position_ids?: array<int,int>
     * } $filters
     * @return Collection<int, WorkShiftAssignment>
     */
    public function feed(int $companyId, int $scheduleId, array $filters): Collection;

    /**
     * @param array{
     *   company_id:int,
     *   work_schedule_id:int,
     *   employee_id:int,
     *   work_shift_id:int,
     *   date:string
     * } $payload
     */
    public function create(array $payload): WorkShiftAssignment;

    /**
     * @param array{
     *   work_schedule_id:int,
     *   employee_id:int,
     *   work_shift_id:int,
     *   date:string
     * } $payload
     */
    public function updateAssignment(int $companyId, int $id, array $payload): WorkShiftAssignment;

    public function deleteAssignment(int $companyId, int $id): bool;

    /**
     * @param list<int> $employeeIds
     * @param list<string> $dates
     * @return Collection<int, WorkShiftAssignment>
     */
    public function bulkUpsert(
        int $companyId,
        int $workScheduleId,
        int $workShiftId,
        array $employeeIds,
        array $dates
    ): Collection;

    public function existsForEmployeeDate(int $companyId, int $employeeId, string $date, ?int $ignoreId = null): bool;

    public function findForCompany(int $companyId, int $id): WorkShiftAssignment;
}
