<?php

declare(strict_types=1);

namespace App\Services;

use App\Interfaces\WorkScheduleAssignmentRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * WorkScheduleAssignment üzleti logikai réteg.
 *
 * A kontrollerréteg és a repository között biztosít egységes
 * domain API-t a schedule-alapú kiosztásokhoz.
 */
class WorkScheduleAssignmentService
{
    /**
     * @param WorkScheduleAssignmentRepositoryInterface $repo
     */
    public function __construct(
        private readonly WorkScheduleAssignmentRepositoryInterface $repo
    ) {}

    /**
     * @param array{
     *   search?: string|null,
     *   day?: string|null,
     *   page?: int,
     *   per_page?: int,
     *   field?: string|null,
     *   order?: 'asc'|'desc'|null
     * } $filters
     */
    public function fetchBySchedule(int $scheduleId, int $companyId, array $filters): LengthAwarePaginator
    {
        return $this->repo->fetchBySchedule($scheduleId, $companyId, $filters);
    }

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
     * } $payload
     */
    public function store(array $payload): \App\Models\WorkScheduleAssignment
    {
        return $this->repo->store($payload);
    }

    /**
     * @param array{
     *   employee_id: int,
     *   work_shift_id: int,
     *   day: string,
     *   start_time?: string|null,
     *   end_time?: string|null,
     *   meta?: array<string,mixed>|null
     * } $payload
     */
    public function update(int $id, int $scheduleId, int $companyId, array $payload): \App\Models\WorkScheduleAssignment
    {
        return $this->repo->updateAssignment($id, $scheduleId, $companyId, $payload);
    }

    /**
     * Egy kiosztás soft delete törlése schedule scope-ban.
     *
     * @param int $id Kiosztás azonosító
     * @param int $scheduleId Beosztás azonosító
     * @param int $companyId Tenant cég azonosító
     * @return bool Törlés sikeressége
     */
    public function destroy(int $id, int $scheduleId, int $companyId): bool
    {
        return $this->repo->destroy($id, $scheduleId, $companyId);
    }

    /**
     * @param list<int> $ids
     */
    public function bulkDelete(array $ids, int $scheduleId, int $companyId): int
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));

        return $this->repo->bulkDelete($ids, $scheduleId, $companyId);
    }
}
