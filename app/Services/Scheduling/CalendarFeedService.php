<?php

declare(strict_types=1);

namespace App\Services\Scheduling;

use App\Services\AbsenceService;
use App\Services\WorkScheduleAssignmentService;

class CalendarFeedService
{
    public function __construct(
        private readonly WorkScheduleAssignmentService $assignmentService,
        private readonly AbsenceService $absenceService,
    ) {
    }

    public function feed(int $companyId, int $scheduleId, array $filters, bool $includeAbsences = true): array
    {
        $assignmentFeed = $this->assignmentService->feed($companyId, $scheduleId, $filters);
        $absenceEvents = $includeAbsences
            ? $this->absenceService->fetchCalendarEvents($companyId, [
                'date_from' => $assignmentFeed['range']['start'],
                'date_to' => $assignmentFeed['range']['end'],
                'employee_ids' => $filters['employee_ids'] ?? [],
            ])
            : [];

        return [
            'events' => array_values([
                ...$assignmentFeed['events'],
                ...$absenceEvents,
            ]),
            'range' => $assignmentFeed['range'],
            'selected_date' => $assignmentFeed['selected_date'],
            'editable' => $assignmentFeed['editable'],
        ];
    }
}
