<?php

declare(strict_types=1);

namespace App\Data\WorkShiftAssignment;

use App\Models\WorkShiftAssignment;
use Spatie\LaravelData\Data;

class WorkShiftAssignmentData extends Data
{
    public function __construct(
        public int $id,
        public int $company_id,
        public int $work_schedule_id,
        public int $employee_id,
        public int $work_shift_id,
        public string $date,
        public ?string $work_pattern_name = null,
        public ?string $work_schedule_name = null,
        public ?string $employee_name = null,
        public ?string $created_at = null,
        public ?string $updated_at = null,
    ) {}

    public static function fromModel(WorkShiftAssignment $assignment): self
    {
        $assignment->loadMissing(['employee:id,first_name,last_name', 'workSchedule:id,name']);

        return new self(
            id: (int) $assignment->id,
            company_id: (int) $assignment->company_id,
            work_schedule_id: (int) $assignment->work_schedule_id,
            employee_id: (int) $assignment->employee_id,
            work_shift_id: (int) $assignment->work_shift_id,
            date: (string) optional($assignment->date)?->format('Y-m-d'),
            work_pattern_name: $assignment->getAttribute('work_pattern_name'),
            work_schedule_name: (string) ($assignment->workSchedule?->name ?? ''),
            employee_name: trim((string) (($assignment->employee?->last_name ?? '') . ' ' . ($assignment->employee?->first_name ?? ''))),
            created_at: optional($assignment->created_at)?->toDateTimeString(),
            updated_at: optional($assignment->updated_at)?->toDateTimeString(),
        );
    }
}
