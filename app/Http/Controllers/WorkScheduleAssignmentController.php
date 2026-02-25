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
use App\Models\WorkShiftAssignment;
use App\Policies\WorkScheduleAssignmentPolicy;
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
        private readonly WorkScheduleAssignmentService $service,
        private readonly CurrentCompanyContext $companyContext
    ) {}

    public function calendar(CalendarPageRequest $request): InertiaResponse
    {
        $this->authorize(WorkScheduleAssignmentPolicy::PERM_VIEW_ANY, WorkShiftAssignment::class);

        $companyId = $this->requireCurrentCompanyId($request);
        $schedules = $this->service->getSchedulesForSelector($companyId)
            ->map(fn ($s): array => [
                'id' => (int) $s->id,
                'company_id' => (int) $s->company_id,
                'name' => (string) $s->name,
                'date_from' => (string) $s->date_from->format('Y-m-d'),
                'date_to' => (string) $s->date_to->format('Y-m-d'),
                'status' => (string) $s->status,
            ])
            ->values()
            ->all();

        return Inertia::render('Scheduling/Calendar/Index', [
            'title' => 'Naptár tervező',
            'current_company_id' => $companyId,
            'schedules' => $schedules,
            'permissions' => [
                'viewer' => $request->user()?->can(WorkScheduleAssignmentPolicy::PERM_VIEW_ANY, WorkShiftAssignment::class) ?? false,
                'planner' => (
                    ($request->user()?->can(WorkScheduleAssignmentPolicy::PERM_CREATE, WorkShiftAssignment::class) ?? false)
                    && ($request->user()?->can(WorkScheduleAssignmentPolicy::PERM_UPDATE, WorkShiftAssignment::class) ?? false)
                    && ($request->user()?->can(WorkScheduleAssignmentPolicy::PERM_DELETE, WorkShiftAssignment::class) ?? false)
                ),
            ],
        ]);
    }

    public function feed(FeedRequest $request): JsonResponse
    {
        $this->authorize(WorkScheduleAssignmentPolicy::PERM_VIEW_ANY, WorkShiftAssignment::class);

        $companyId = $this->requireCurrentCompanyId($request);
        $data = $request->validated();

        $result = $this->service->feed(
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
            ]
        );

        return response()->json([
            'message' => 'Naptár események sikeresen lekérve.',
            'data' => $result['events'],
            'meta' => [
                'range' => $result['range'],
                'selected_date' => $result['selected_date'],
                'editable' => $result['editable'],
            ],
        ], Response::HTTP_OK);
    }

    public function store(StoreRequest $request): JsonResponse
    {
        $this->authorize(WorkScheduleAssignmentPolicy::PERM_CREATE, WorkShiftAssignment::class);

        $companyId = $this->requireCurrentCompanyId($request);
        $created = $this->service->create($companyId, $request->validated());

        return response()->json([
            'message' => 'Beosztás hozzárendelés létrehozva.',
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
            'message' => 'Beosztás hozzárendelés frissítve.',
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
            'message' => $deleted ? 'Beosztás hozzárendelés törölve.' : 'Törlés sikertelen.',
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
            'message' => 'Bulk hozzárendelés sikeres.',
            'data' => $rows,
            'count' => count($rows),
        ], Response::HTTP_OK);
    }

    private function requireCurrentCompanyId(Request $request): int
    {
        return $this->companyContext->resolve($request);
    }
}
