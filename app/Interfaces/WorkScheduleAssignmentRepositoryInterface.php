<?php

declare(strict_types=1);

namespace App\Interfaces;

use App\Models\WorkScheduleAssignment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * WorkScheduleAssignment repository interfész.
 *
 * A schedule-alapú kiosztások adat-hozzáférési szerződését definiálja.
 */
interface WorkScheduleAssignmentRepositoryInterface
{
    /**
     * @param array{
     *   search?: string|null,
     *   day?: string|null,
     *   page?: int,
     *   per_page?: int,
     *   field?: string|null,
     *   order?: 'asc'|'desc'|null
     * } $filters
     *
     * @return LengthAwarePaginator<int, WorkScheduleAssignment>
     */
    public function fetchBySchedule(
        int $scheduleId,
        int $companyId,
        array $filters
    ): LengthAwarePaginator;

    /**
     * @param array{
     *   company_id: int,
     *   work_schedule_id: int,
     *   employee_id: int,
     *   work_shift_id: int,
     *   day: string,
     *   start_time?: string|null,
     *   end_time?: string|null,
     *   meta?: array<string,mixed>|null
     * } $data
     */
    public function store(array $data): WorkScheduleAssignment;

    /**
     * @param array{
     *   employee_id: int,
     *   work_shift_id: int,
     *   day: string,
     *   start_time?: string|null,
     *   end_time?: string|null,
     *   meta?: array<string,mixed>|null
     * } $data
     */
    public function updateAssignment(
        int $id,
        int $scheduleId,
        int $companyId,
        array $data
    ): WorkScheduleAssignment;

    public function destroy(int $id, int $scheduleId, int $companyId): bool;

    /**
     * @param list<int> $ids
     */
    public function bulkDelete(array $ids, int $scheduleId, int $companyId): int;
}
