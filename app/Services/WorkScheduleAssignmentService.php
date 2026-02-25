<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\WorkScheduleAssignment\CalendarEventData;
use App\Data\WorkScheduleAssignment\WorkScheduleAssignmentData;
use App\Interfaces\WorkScheduleAssignmentRepositoryInterface;
use App\Services\Cache\CacheVersionService;
use App\Services\CacheService;
use App\Models\WorkSchedule;
use App\Models\WorkShiftAssignment;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class WorkScheduleAssignmentService
{
    public function __construct(
        private readonly WorkScheduleAssignmentRepositoryInterface $repository,
        private readonly CacheService $cacheService,
        private readonly CacheVersionService $cacheVersionService
    ) {}

    /**
     * @param array{
     *   view_type: 'week'|'month'|'day',
     *   week_count?: int|null,
     *   week_number?: int|null,
     *   week_year?: int|null,
     *   month?: int|null,
     *   year?: int|null,
     *   date?: string|null,
     *   employee_ids?: array<int,int>,
     *   work_shift_ids?: array<int,int>,
     *   position_ids?: array<int,int>
     * } $filters
     * @return array{
     *   events: array<int, CalendarEventData>,
     *   range: array{start:string,end:string},
     *   selected_date: string,
     *   editable: bool
     * }
     */
    public function feed(int $companyId, int $scheduleId, array $filters): array
    {
        $schedule = $this->findSchedule($companyId, $scheduleId);
        $range = $this->resolveRange($filters);

        $resolvedFilters = [
            'start' => $range['start'],
            'end' => $range['end'],
            'employee_ids' => $filters['employee_ids'] ?? [],
            'work_shift_ids' => $filters['work_shift_ids'] ?? [],
            'position_ids' => $filters['position_ids'] ?? [],
        ];

        $this->validateFilterOwnership($companyId, $resolvedFilters);

        $paramsForKey = [
            'company_id' => $companyId,
            'schedule_id' => (int) $schedule->id,
            'view_type' => $filters['view_type'] ?? 'week',
            'week_count' => $filters['week_count'] ?? null,
            'week_number' => $filters['week_number'] ?? null,
            'week_year' => $filters['week_year'] ?? null,
            'month' => $filters['month'] ?? null,
            'year' => $filters['year'] ?? null,
            'date' => $filters['date'] ?? null,
            'start' => $range['start'],
            'end' => $range['end'],
            'employee_ids' => array_values(array_map('intval', $resolvedFilters['employee_ids'])),
            'work_shift_ids' => array_values(array_map('intval', $resolvedFilters['work_shift_ids'])),
            'position_ids' => array_values(array_map('intval', $resolvedFilters['position_ids'])),
        ];
        ksort($paramsForKey);

        $version = $this->cacheVersionService->get('work_schedule_assignments');
        $hash = hash('sha256', json_encode($paramsForKey, JSON_THROW_ON_ERROR));
        $key = "v{$version}:feed:{$hash}";

        /** @var array<int, CalendarEventData> $events */
        $events = $this->cacheService->remember(
            tag: 'work_schedule_assignments_feed',
            key: $key,
            callback: function () use ($companyId, $schedule, $resolvedFilters): array {
                $rows = $this->repository->feed($companyId, (int) $schedule->id, $resolvedFilters);
                return CalendarEventData::collect($rows)->all();
            },
            ttl: (int) config('cache.ttl_fetch', 60)
        );

        return [
            'events' => $events,
            'range' => [
                'start' => $range['start'],
                'end' => $range['end'],
            ],
            'selected_date' => $range['selected_date'],
            'editable' => $range['editable'],
        ];
    }

    public function create(int $companyId, array $payload): WorkScheduleAssignmentData
    {
        $schedule = $this->repository->findScheduleForCompany($companyId, (int) $payload['work_schedule_id']);
        $this->guardPlannerWritable($schedule);

        $employee = $this->repository->findEmployeeForCompany($companyId, (int) $payload['employee_id']);
        $shift = $this->repository->findShiftForCompany($companyId, (int) $payload['work_shift_id']);
        $date = (string) $payload['date'];

        $this->guardDateWritable($date);
        $this->validateUniqueEmployeeDate($companyId, (int) $employee->id, $date);

        $row = $this->repository->store($companyId, [
            'work_schedule_id' => (int) $schedule->id,
            'employee_id' => (int) $employee->id,
            'work_shift_id' => (int) $shift->id,
            'date' => $date,
        ]);

        return WorkScheduleAssignmentData::fromModel($row);
    }

    public function update(int $companyId, int $id, array $payload): WorkScheduleAssignmentData
    {
        $existing = $this->repository->findOrFailScoped($id, $companyId);
        $schedule = $this->repository->findScheduleForCompany($companyId, (int) $payload['work_schedule_id']);
        $this->guardPlannerWritable($schedule);

        $employee = $this->repository->findEmployeeForCompany($companyId, (int) $payload['employee_id']);
        $shift = $this->repository->findShiftForCompany($companyId, (int) $payload['work_shift_id']);
        $date = (string) $payload['date'];

        $this->guardDateWritable($date);
        $this->validateUniqueEmployeeDate($companyId, (int) $employee->id, $date, (int) $existing->id);

        $row = $this->repository->update($existing, [
            'work_schedule_id' => (int) $schedule->id,
            'employee_id' => (int) $employee->id,
            'work_shift_id' => (int) $shift->id,
            'date' => $date,
        ]);

        return WorkScheduleAssignmentData::fromModel($row);
    }

    public function delete(int $companyId, int $id): bool
    {
        $assignment = $this->repository->findOrFailScoped($id, $companyId);
        $schedule = $this->repository->findScheduleForCompany($companyId, (int) $assignment->work_schedule_id);
        $this->guardPlannerWritable($schedule);
        $this->guardDateWritable((string) $assignment->date->format('Y-m-d'));

        return $this->repository->delete($assignment);
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

        $this->repository->findShiftForCompany($companyId, $workShiftId);

        foreach ($employeeIds as $employeeId) {
            $this->repository->findEmployeeForCompany($companyId, (int) $employeeId);
        }

        foreach ($dates as $date) {
            $this->guardDateWritable($date);
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

    public function findAssignmentForCompany(int $companyId, int $id): WorkShiftAssignment
    {
        return $this->repository->findOrFailScoped($id, $companyId);
    }

    public function getSchedulesForSelector(int $companyId): Collection
    {
        return $this->repository->getSchedulesForSelector($companyId);
    }

    private function findSchedule(int $companyId, int $scheduleId): WorkSchedule
    {
        return $this->repository->findScheduleForCompany($companyId, $scheduleId);
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
            if (! $this->repository->employeesBelongToCompany($companyId, $employeeIds)) {
                throw ValidationException::withMessages(['employee_ids' => 'A kiválasztott dolgozók között cégidegen elem található.']);
            }
        }

        $workShiftIds = $filters['work_shift_ids'] ?? [];
        if (!empty($workShiftIds)) {
            if (! $this->repository->shiftsBelongToCompany($companyId, $workShiftIds)) {
                throw ValidationException::withMessages(['work_shift_ids' => 'A kiválasztott műszakok között cégidegen elem található.']);
            }
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

    private function guardDateWritable(string $date): void
    {
        $selectedDate = CarbonImmutable::parse($date)->startOfDay();
        if ($selectedDate->lessThan(CarbonImmutable::today())) {
            throw new AuthorizationException('Múltbeli nap nem módosítható.');
        }
    }

    /**
     * @param array{
     *   view_type?: string,
     *   week_count?: int|null,
     *   week_number?: int|null,
     *   week_year?: int|null,
     *   month?: int|null,
     *   year?: int|null,
     *   date?: string|null
     * } $filters
     * @return array{start:string,end:string,selected_date:string,editable:bool}
     */
    private function resolveRange(array $filters): array
    {
        $today = CarbonImmutable::today();
        $viewType = (string) ($filters['view_type'] ?? 'week');

        if ($viewType === 'day') {
            $selected = isset($filters['date']) && is_string($filters['date']) && $filters['date'] !== ''
                ? CarbonImmutable::parse($filters['date'])->startOfDay()
                : $today;

            return [
                'start' => $selected->toDateString(),
                'end' => $selected->toDateString(),
                'selected_date' => $selected->toDateString(),
                'editable' => $selected->greaterThanOrEqualTo($today),
            ];
        }

        if ($viewType === 'month') {
            $month = (int) ($filters['month'] ?? (int) $today->format('m'));
            $year = (int) ($filters['year'] ?? (int) $today->format('Y'));
            $start = CarbonImmutable::create($year, $month, 1)->startOfMonth();
            $end = $start->endOfMonth();

            return [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
                'selected_date' => $end->toDateString(),
                'editable' => $end->greaterThanOrEqualTo($today),
            ];
        }

        $weekNumber = isset($filters['week_number']) ? (int) $filters['week_number'] : null;
        if ($weekNumber !== null && $weekNumber > 0) {
            $weekYear = (int) ($filters['week_year'] ?? (int) $today->format('Y'));
            $maxWeek = (int) CarbonImmutable::create($weekYear, 12, 28)->isoWeek();
            $resolvedWeek = max(1, min($maxWeek, $weekNumber));
            $start = CarbonImmutable::now()->setISODate($weekYear, $resolvedWeek, 1)->startOfDay();
        } else {
            $weekCount = max(1, min(12, (int) ($filters['week_count'] ?? 1)));
            $start = $today->startOfWeek()->subWeeks($weekCount - 1);
        }

        $end = $start->endOfWeek();

        return [
            'start' => $start->toDateString(),
            'end' => $end->toDateString(),
            'selected_date' => $end->toDateString(),
            'editable' => $end->greaterThanOrEqualTo($today),
        ];
    }

    private function guardPlannerWritable(WorkSchedule $schedule): void
    {
        if ((string) $schedule->status === 'published') {
            throw new AuthorizationException('Published beosztás nem módosítható.');
        }
    }
}
