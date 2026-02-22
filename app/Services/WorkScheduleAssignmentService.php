<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\WorkScheduleAssignment\CalendarEventData;
use App\Data\WorkScheduleAssignment\WorkScheduleAssignmentData;
use App\Interfaces\WorkScheduleAssignmentRepositoryInterface;
use App\Models\Employee;
use App\Models\WorkSchedule;
use App\Models\WorkShift;
use App\Models\WorkShiftAssignment;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class WorkScheduleAssignmentService
{
    public function __construct(
        private readonly WorkScheduleAssignmentRepositoryInterface $repository
    ) {}

    /**
     * @param array{
     *   start?: string|null,
     *   end?: string|null,
     *   employee_ids?: array<int,int>,
     *   work_shift_ids?: array<int,int>,
     *   position_ids?: array<int,int>
     * } $filters
     * @return array<int, CalendarEventData>
     */
    public function feed(int $companyId, int $scheduleId, array $filters): array
    {
        $schedule = $this->findSchedule($companyId, $scheduleId);

        $this->validateFilterOwnership($companyId, $filters);
        $rows = $this->repository->feed($companyId, (int) $schedule->id, $filters);

        return CalendarEventData::collect($rows)->all();
    }

    public function create(int $companyId, array $payload): WorkScheduleAssignmentData
    {
        $schedule = $this->findSchedule($companyId, (int) $payload['work_schedule_id']);
        $this->guardPlannerWritable($schedule);

        $employee = $this->findEmployee($companyId, (int) $payload['employee_id']);
        $shift = $this->findShift($companyId, (int) $payload['work_shift_id']);
        $date = (string) $payload['date'];

        $this->validateDateInScheduleRange($schedule, $date);
        $this->validateUniqueEmployeeDate($companyId, (int) $employee->id, $date);

        $row = $this->repository->create([
            'company_id' => $companyId,
            'work_schedule_id' => (int) $schedule->id,
            'employee_id' => (int) $employee->id,
            'work_shift_id' => (int) $shift->id,
            'date' => $date,
        ]);

        return WorkScheduleAssignmentData::fromModel($row);
    }

    public function update(int $companyId, int $id, array $payload): WorkScheduleAssignmentData
    {
        $existing = $this->repository->findForCompany($companyId, $id);
        $schedule = $this->findSchedule($companyId, (int) $payload['work_schedule_id']);
        $this->guardPlannerWritable($schedule);

        $employee = $this->findEmployee($companyId, (int) $payload['employee_id']);
        $shift = $this->findShift($companyId, (int) $payload['work_shift_id']);
        $date = (string) $payload['date'];

        $this->validateDateInScheduleRange($schedule, $date);
        $this->validateUniqueEmployeeDate($companyId, (int) $employee->id, $date, (int) $existing->id);

        $row = $this->repository->updateAssignment($companyId, $id, [
            'work_schedule_id' => (int) $schedule->id,
            'employee_id' => (int) $employee->id,
            'work_shift_id' => (int) $shift->id,
            'date' => $date,
        ]);

        return WorkScheduleAssignmentData::fromModel($row);
    }

    public function delete(int $companyId, int $id): bool
    {
        $assignment = $this->repository->findForCompany($companyId, $id);
        $schedule = $this->findSchedule($companyId, (int) $assignment->work_schedule_id);
        $this->guardPlannerWritable($schedule);

        return $this->repository->deleteAssignment($companyId, $id);
    }

    /**
     * @param list<int> $employeeIds
     * @param list<string> $dates
     * @return array<int, WorkScheduleAssignmentData>
     */
    public function bulkUpsert(
        int $companyId,
        int $workScheduleId,
        int $workShiftId,
        array $employeeIds,
        array $dates
    ): array {
        $schedule = $this->findSchedule($companyId, $workScheduleId);
        $this->guardPlannerWritable($schedule);

        $this->findShift($companyId, $workShiftId);

        foreach ($employeeIds as $employeeId) {
            $this->findEmployee($companyId, (int) $employeeId);
        }

        foreach ($dates as $date) {
            $this->validateDateInScheduleRange($schedule, $date);
        }

        $rows = $this->repository->bulkUpsert(
            companyId: $companyId,
            workScheduleId: $workScheduleId,
            workShiftId: $workShiftId,
            employeeIds: $employeeIds,
            dates: $dates
        );

        return WorkScheduleAssignmentData::collect($rows)->all();
    }

    public function getSchedulesForSelector(int $companyId): Collection
    {
        return WorkSchedule::query()
            ->where('company_id', $companyId)
            ->orderByDesc('date_from')
            ->get(['id', 'company_id', 'name', 'date_from', 'date_to', 'status']);
    }

    private function findSchedule(int $companyId, int $scheduleId): WorkSchedule
    {
        /** @var WorkSchedule $schedule */
        $schedule = WorkSchedule::query()
            ->where('company_id', $companyId)
            ->findOrFail($scheduleId);

        return $schedule;
    }

    private function findEmployee(int $companyId, int $employeeId): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::query()
            ->where('company_id', $companyId)
            ->findOrFail($employeeId);

        return $employee;
    }

    private function findShift(int $companyId, int $workShiftId): WorkShift
    {
        /** @var WorkShift $shift */
        $shift = WorkShift::query()
            ->where('company_id', $companyId)
            ->findOrFail($workShiftId);

        return $shift;
    }

    /**
     * @param array{
     *   employee_ids?: array<int,int>,
     *   work_shift_ids?: array<int,int>,
     *   position_ids?: array<int,int>
     * } $filters
     */
    private function validateFilterOwnership(int $companyId, array $filters): void
    {
        $employeeIds = $filters['employee_ids'] ?? [];
        if (!empty($employeeIds)) {
            $count = Employee::query()->where('company_id', $companyId)->whereIn('id', $employeeIds)->count();
            if ($count !== count($employeeIds)) {
                throw ValidationException::withMessages(['employee_ids' => 'A kiválasztott dolgozók között cégidegen elem található.']);
            }
        }

        $workShiftIds = $filters['work_shift_ids'] ?? [];
        if (!empty($workShiftIds)) {
            $count = WorkShift::query()->where('company_id', $companyId)->whereIn('id', $workShiftIds)->count();
            if ($count !== count($workShiftIds)) {
                throw ValidationException::withMessages(['work_shift_ids' => 'A kiválasztott műszakok között cégidegen elem található.']);
            }
        }
    }

    private function validateDateInScheduleRange(WorkSchedule $schedule, string $date): void
    {
        $from = (string) $schedule->date_from->format('Y-m-d');
        $to = (string) $schedule->date_to->format('Y-m-d');

        if ($date < $from || $date > $to) {
            throw ValidationException::withMessages([
                'date' => 'A dátumnak a beosztás intervallumába kell esnie.',
            ]);
        }
    }

    private function validateUniqueEmployeeDate(int $companyId, int $employeeId, string $date, ?int $ignoreId = null): void
    {
        if ($this->repository->existsForEmployeeDate($companyId, $employeeId, $date, $ignoreId)) {
            throw ValidationException::withMessages([
                'employee_id' => 'Az adott dolgozónak erre a napra már van műszakbeosztása.',
            ]);
        }
    }

    private function guardPlannerWritable(WorkSchedule $schedule): void
    {
        if ((string) $schedule->status === 'published') {
            throw new AuthorizationException('Published beosztás nem módosítható.');
        }
    }
}
