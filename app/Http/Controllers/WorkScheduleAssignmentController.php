<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\WorkScheduleAssignment\WorkScheduleAssignmentData;
use App\Http\Requests\WorkScheduleAssignment\BulkUpsertRequest;
use App\Http\Requests\WorkScheduleAssignment\CalendarPageRequest;
use App\Http\Requests\WorkScheduleAssignment\DeleteRequest;
use App\Http\Requests\WorkScheduleAssignment\FeedRequest;
use App\Http\Requests\WorkScheduleAssignment\StoreRequest;
use App\Http\Requests\WorkScheduleAssignment\UpdateRequest;
use App\Models\EmployeeAbsence;
use App\Models\WorkShiftAssignment;
use App\Policies\EmployeeAbsencePolicy;
use App\Policies\WorkScheduleAssignmentPolicy;
use App\Services\Scheduling\CalendarFeedService;
use App\Services\MonthClosureService;
use App\Services\WorkScheduleAssignmentService;
use App\Support\CurrentCompanyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

class WorkScheduleAssignmentController extends Controller
{
    public function __construct(
        private readonly CalendarFeedService $calendarFeedService,
        private readonly WorkScheduleAssignmentService $service,
        private readonly CurrentCompanyContext $companyContext,
        private readonly MonthClosureService $monthClosureService,
    ) {}

    public function calendar(CalendarPageRequest $request): InertiaResponse
    {
        $this->authorize(WorkScheduleAssignmentPolicy::PERM_VIEW_ANY, WorkShiftAssignment::class);

        $companyId = $this->requireCurrentCompanyId($request);
        $schedules = $this->service->getSchedulesForSelector($companyId)
            ->map(fn ($s): array => [
                'id' => (int) data_get($s, 'id'),
                'company_id' => (int) data_get($s, 'company_id'),
                'name' => (string) data_get($s, 'name'),
                'date_from' => (string) data_get($s, 'date_from'),
                'date_to' => (string) data_get($s, 'date_to'),
                'status' => (string) data_get($s, 'status'),
            ])
            ->values()
            ->all();

        return Inertia::render('Scheduling/Calendar/Index', [
            'current_company_id' => $companyId,
            'schedules' => $schedules,
            'month_lock' => $this->monthClosureService->stateForMonth(
                $companyId,
                (int) now()->format('Y'),
                (int) now()->format('m'),
            ),
            'permissions' => [
                'viewer' => $request->user()?->can(WorkScheduleAssignmentPolicy::PERM_VIEW_ANY, WorkShiftAssignment::class) ?? false,
                'planner' => (
                    ($request->user()?->can(WorkScheduleAssignmentPolicy::PERM_CREATE, WorkShiftAssignment::class) ?? false)
                    && ($request->user()?->can(WorkScheduleAssignmentPolicy::PERM_UPDATE, WorkShiftAssignment::class) ?? false)
                    && ($request->user()?->can(WorkScheduleAssignmentPolicy::PERM_DELETE, WorkShiftAssignment::class) ?? false)
                ),
                'absenceViewer' => $request->user()?->can(EmployeeAbsencePolicy::PERM_VIEW_ANY, EmployeeAbsence::class) ?? false,
                'absencePlanner' => (
                    ($request->user()?->can(EmployeeAbsencePolicy::PERM_CREATE, EmployeeAbsence::class) ?? false)
                    && ($request->user()?->can(EmployeeAbsencePolicy::PERM_UPDATE, EmployeeAbsence::class) ?? false)
                    && ($request->user()?->can(EmployeeAbsencePolicy::PERM_DELETE, EmployeeAbsence::class) ?? false)
                ),
                'monthClosureViewAny' => $request->user()?->can(\App\Policies\MonthClosurePolicy::PERM_VIEW_ANY, \App\Models\MonthClosure::class) ?? false,
                'monthClosureClose' => $request->user()?->can(\App\Policies\MonthClosurePolicy::PERM_CREATE, \App\Models\MonthClosure::class) ?? false,
                'monthClosureReopen' => $request->user()?->can(\App\Policies\MonthClosurePolicy::PERM_DELETE, \App\Models\MonthClosure::class) ?? false,
            ],
        ]);
    }

    public function feed(FeedRequest $request): JsonResponse
    {
        $this->authorize(WorkScheduleAssignmentPolicy::PERM_VIEW_ANY, WorkShiftAssignment::class);

        $companyId = $this->requireCurrentCompanyId($request);
        $data = $request->validated();

        $result = $this->calendarFeedService->feed(
            companyId: $companyId,
            scheduleId: (int) $data['schedule_id'],
            filters: [
                'view_type' => (string) ($data['view_type'] ?? 'week'),
                'week_count' => isset($data['week_count']) ? (int) $data['week_count'] : null,
                'week_number' => isset($data['week_number']) ? (int) $data['week_number'] : null,
                'week_year' => isset($data['week_year']) ? (int) $data['week_year'] : null,
                'month' => isset($data['month']) ? (int) $data['month'] : null,
                'year' => isset($data['year']) ? (int) $data['year'] : null,
                'date' => isset($data['date']) ? (string) $data['date'] : null,
                'employee_ids' => array_values(array_map('intval', $data['employee_ids'] ?? [])),
                'work_shift_ids' => array_values(array_map('intval', $data['work_shift_ids'] ?? [])),
                'position_ids' => array_values(array_map('intval', $data['position_ids'] ?? [])),
            ],
            includeAbsences: $request->user()?->can(EmployeeAbsencePolicy::PERM_VIEW_ANY, EmployeeAbsence::class) ?? false
        );

        return response()->json([
            'message' => __('work_schedule_assignments.messages.feed_fetch_success'),
            'data' => $result['events'],
            'meta' => [
                'range' => $result['range'],
                'selected_date' => $result['selected_date'],
                'editable' => $result['editable'],
                'month_lock' => $this->monthClosureService->stateForMonth(
                    $companyId,
                    $this->resolveViewedYear($data),
                    $this->resolveViewedMonth($data),
                ),
                'closed_month_keys' => $this->monthClosureService->closedMonthKeysWithinRange(
                    $companyId,
                    (string) $result['range']['start'],
                    (string) $result['range']['end'],
                ),
            ],
        ], Response::HTTP_OK);
    }

    public function store(StoreRequest $request): JsonResponse
    {
        $this->authorize(WorkScheduleAssignmentPolicy::PERM_CREATE, WorkShiftAssignment::class);

        $companyId = $this->requireCurrentCompanyId($request);
        $created = $this->service->create($companyId, $request->validated());

        return response()->json([
            'message' => __('work_schedule_assignments.messages.created_success'),
            'data' => $created,
        ], Response::HTTP_CREATED);
    }

    public function update(UpdateRequest $request, int $id): JsonResponse
    {
        $companyId = $this->requireCurrentCompanyId($request);
        $assignment = $this->service->findAssignmentForCompany($companyId, $id);
        $this->authorize(WorkScheduleAssignmentPolicy::PERM_UPDATE, $assignment);
        $updated = $this->service->update($companyId, $id, $request->validated());

        return response()->json([
            'message' => __('work_schedule_assignments.messages.updated_success'),
            'data' => $updated,
        ], Response::HTTP_OK);
    }

    public function destroy(DeleteRequest $request, int $id): JsonResponse
    {
        $companyId = $this->requireCurrentCompanyId($request);
        $assignment = $this->service->findAssignmentForCompany($companyId, $id);
        $this->authorize(WorkScheduleAssignmentPolicy::PERM_DELETE, $assignment);
        $deleted = $this->service->delete($companyId, $id);

        return response()->json([
            'message' => $deleted ? __('work_schedule_assignments.messages.deleted_success') : __('common.delete_failed'),
            'deleted' => $deleted,
        ], $deleted ? Response::HTTP_OK : Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    public function bulkUpsert(BulkUpsertRequest $request): JsonResponse
    {
        $this->authorize(WorkScheduleAssignmentPolicy::PERM_CREATE, WorkShiftAssignment::class);

        $companyId = $this->requireCurrentCompanyId($request);
        $data = $request->validated();

        $rows = $this->service->bulkUpsert(
            companyId: $companyId,
            workScheduleId: (int) $data['work_schedule_id'],
            workShiftId: (int) $data['work_shift_id'],
            employeeIds: array_values(array_map('intval', $data['employee_ids'])),
            dates: array_values(array_map('strval', $data['dates']))
        );

        return response()->json([
            'message' => __('work_schedule_assignments.messages.bulk_assign_success'),
            'data' => $rows,
            'count' => count($rows),
        ], Response::HTTP_OK);
    }

    private function requireCurrentCompanyId(Request $request): int
    {
        return $this->companyContext->resolve($request);
    }

    private function resolveViewedYear(array $filters): int
    {
        if (($filters['view_type'] ?? 'week') === 'month') {
            return (int) ($filters['year'] ?? now()->format('Y'));
        }

        if (($filters['view_type'] ?? 'week') === 'day' && isset($filters['date'])) {
            return (int) \Carbon\CarbonImmutable::parse((string) $filters['date'])->format('Y');
        }

        if (isset($filters['week_year'])) {
            return (int) $filters['week_year'];
        }

        return (int) now()->format('Y');
    }

    private function resolveViewedMonth(array $filters): int
    {
        if (($filters['view_type'] ?? 'week') === 'month') {
            return (int) ($filters['month'] ?? now()->format('m'));
        }

        if (($filters['view_type'] ?? 'week') === 'day' && isset($filters['date'])) {
            return (int) \Carbon\CarbonImmutable::parse((string) $filters['date'])->format('m');
        }

        if (isset($filters['week_number']) && isset($filters['week_year'])) {
            return (int) \Carbon\CarbonImmutable::now()
                ->setISODate((int) $filters['week_year'], (int) $filters['week_number'], 1)
                ->format('m');
        }

        return (int) now()->format('m');
    }
}
