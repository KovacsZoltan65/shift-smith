<?php

declare(strict_types=1);

namespace App\Data\WorkScheduleAssignment;

use App\Models\WorkShiftAssignment;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

class CalendarEventData extends Data
{
    public function __construct(
        public int $id,
        public string $title,
        public string $start,
        public string $end,
        public bool $allDay,
        public bool $editable,
        /** @var list<string> */
        public array $className,
        /** @var array{
         *   employee_id:int,
         *   employee_name:string,
         *   shift_id:int,
         *   shift_name:string,
         *   shift_start_time:string,
         *   shift_end_time:string,
         *   schedule_id:int,
         *   editable:bool
         * } */
        public array $extendedProps
    ) {}

    public static function fromModel(WorkShiftAssignment $row): self
    {
        $row->loadMissing(['employee', 'workShift']);

        $employeeName = trim((string) (($row->employee?->last_name ?? '') . ' ' . ($row->employee?->first_name ?? '')));
        $shiftName = (string) ($row->workShift?->name ?? '');
        $startTime = (string) ($row->workShift?->start_time ?? '');
        $endTime = (string) ($row->workShift?->end_time ?? '');
        $date = CarbonImmutable::parse((string) $row->date)->startOfDay();
        $editable = $date->greaterThanOrEqualTo(CarbonImmutable::today());

        $timeLabel = ($startTime !== '' || $endTime !== '')
            ? sprintf(' %s-%s', $startTime !== '' ? substr($startTime, 0, 5) : '--:--', $endTime !== '' ? substr($endTime, 0, 5) : '--:--')
            : '';

        return new self(
            id: (int) $row->id,
            title: trim(sprintf('%s - %s%s', $employeeName, $shiftName, $timeLabel)),
            start: $date->toDateString(),
            end: $date->addDay()->toDateString(),
            allDay: true,
            editable: $editable,
            className: ['shift-' . (int) $row->work_shift_id],
            extendedProps: [
                'employee_id' => (int) $row->employee_id,
                'employee_name' => $employeeName,
                'shift_id' => (int) $row->work_shift_id,
                'shift_name' => $shiftName,
                'shift_start_time' => $startTime,
                'shift_end_time' => $endTime,
                'schedule_id' => (int) $row->work_schedule_id,
                'editable' => $editable,
            ]
        );
    }
}
