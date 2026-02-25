<?php

declare(strict_types=1);

namespace App\Interfaces;

use App\Models\Employee;
use App\Models\WorkSchedule;
use App\Models\WorkShift;
use App\Models\WorkShiftAssignment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface WorkScheduleAssignmentRepositoryInterface
{
    /**
     * @param array{
     *   search?: string|null,
     *   schedule_id?: int|null,
     *   employee_id?: int|null,
     *   work_shift_id?: int|null,
     *   date_from?: string|null,
     *   date_to?: string|null,
     *   field?: string|null,
     *   order?: 'asc'|'desc'|null,
     *   per_page?: int|null,
     *   page?: int|null
     * } $filters
     * @return LengthAwarePaginator<int, WorkShiftAssignment>
     */
    public function paginate(int $companyId, array $filters): LengthAwarePaginator;

    /**
     * @param array{
     *   search?: string|null,
     *   schedule_id?: int|null,
     *   employee_id?: int|null,
     *   work_shift_id?: int|null,
     *   date_from?: string|null,
     *   date_to?: string|null,
     *   field?: string|null,
     *   order?: 'asc'|'desc'|null
     * } $filters
     * @return Collection<int, WorkShiftAssignment>
     */
    public function fetch(int $companyId, array $filters): Collection;

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

    public function findOrFailScoped(int $id, int $companyId): WorkShiftAssignment;

    /**
     * @param array{
     *   work_schedule_id:int,
     *   employee_id:int,
     *   work_shift_id:int,
     *   date:string
     * } $payload
     */
    public function store(int $companyId, array $payload): WorkShiftAssignment;

    /**
     * @param array{
     *   work_schedule_id:int,
     *   employee_id:int,
     *   work_shift_id:int,
     *   date:string
     * } $payload
     */
    public function update(WorkShiftAssignment $assignment, array $payload): WorkShiftAssignment;

    public function delete(WorkShiftAssignment $assignment): bool;

    /**
     * @param list<int> $ids
     */
    public function bulkDelete(array $ids, int $companyId): int;

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

    /**
     * @return Collection<int, WorkSchedule>
     */
    public function getSchedulesForSelector(int $companyId): Collection;

    public function findScheduleForCompany(int $companyId, int $scheduleId): WorkSchedule;

    public function findEmployeeForCompany(int $companyId, int $employeeId): Employee;

    public function findShiftForCompany(int $companyId, int $workShiftId): WorkShift;

    /**
     * @param list<int> $employeeIds
     */
    public function employeesBelongToCompany(int $companyId, array $employeeIds): bool;

    /**
     * @param list<int> $shiftIds
     */
    public function shiftsBelongToCompany(int $companyId, array $shiftIds): bool;

    public function findScheduleOrFailScoped(int $scheduleId, int $companyId): WorkSchedule;

    public function findEmployeeOrFailScoped(int $employeeId, int $companyId): Employee;

    public function findShiftOrFailScoped(int $workShiftId, int $companyId): WorkShift;

    /**
     * @param list<int> $employeeIds
     */
    public function countEmployeesScoped(array $employeeIds, int $companyId): int;

    /**
     * @param list<int> $shiftIds
     */
    public function countShiftsScoped(array $shiftIds, int $companyId): int;
}
