<?php

declare(strict_types=1);

namespace App\Interfaces;

use App\Models\WorkSchedule;
use App\Models\WorkShiftAssignment;
use Illuminate\Support\Collection;

interface WorkShiftAssignmentRepositoryInterface
{
    /**
     * @return Collection<int, WorkShiftAssignment>
     */
    public function listByWorkShift(int $workShiftId): Collection;

    public function upsertByEmployeeAndDate(
        int $companyId,
        int $workShiftId,
        int $workScheduleId,
        int $employeeId,
        string $date
    ): WorkShiftAssignment;

    /**
     * @return Collection<int, WorkSchedule>
     */
    public function getSchedulesForCompany(int $companyId): Collection;

    public function deleteForWorkShift(int $workShiftId, int $id): bool;
}
