<?php

declare(strict_types=1);

namespace App\Data\WorkScheduleAssignment;

use App\Models\WorkShiftAssignment;
use Spatie\LaravelData\Data;

class WorkScheduleAssignmentData extends Data
{
    public function __construct(
        public int $id,
        public int $company_id,
        public int $work_schedule_id,
        public int $employee_id,
        public int $work_shift_id,
        public string $date,
        public ?string $employee_name = null,
        public ?string $work_shift_name = null,
        public ?string $work_schedule_name = null
    ) {}

    public static function fromModel(WorkShiftAssignment $assignment): self
    {
        $assignment->loadMissing(['employee:id,first_name,last_name', 'workShift:id,name', 'workSchedule:id,name']);

        return new self(
            id: (int) $assignment->id,
            company_id: (int) $assignment->company_id,
            work_schedule_id: (int) $assignment->work_schedule_id,
            employee_id: (int) $assignment->employee_id,
            work_shift_id: (int) $assignment->work_shift_id,
            date: (string) $assignment->date->format('Y-m-d'),
            employee_name: trim((string) (($assignment->employee?->last_name ?? '') . ' ' . ($assignment->employee?->first_name ?? ''))),
            work_shift_name: $assignment->workShift?->name,
            work_schedule_name: $assignment->workSchedule?->name
        );
    }
}
