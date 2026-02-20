<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\WorkScheduleAssignment\BulkDeleteRequest;
use App\Http\Requests\WorkScheduleAssignment\DeleteRequest;
use App\Http\Requests\WorkScheduleAssignment\FetchRequest;
use App\Http\Requests\WorkScheduleAssignment\StoreRequest;
use App\Http\Requests\WorkScheduleAssignment\UpdateRequest;
use App\Models\WorkSchedule;
use App\Models\WorkScheduleAssignment;
use App\Policies\WorkScheduleAssignmentPolicy;
use App\Services\WorkScheduleAssignmentService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * WorkScheduleAssignment CRUD controller.
 *
 * Schedule-alapú kiosztások listázása és módosítása tenant-safe módon.
 */
class WorkScheduleAssignmentController extends Controller
{
    /**
     * @param WorkScheduleAssignmentService $service Üzleti logikai szolgáltatás
     */
    public function __construct(
        private readonly WorkScheduleAssignmentService $service
    ) {}

    /**
     * Beosztás szerinti kiosztás-lista lekérése.
     *
     * @param FetchRequest $request Validált szűrő paraméterek
     * @param int $schedule Route paraméter: work_schedule azonosító
     * @return JsonResponse Lapozott lista + meta adatok
     */
    public function fetch(FetchRequest $request, int $schedule): JsonResponse
    {
        $this->authorize(WorkScheduleAssignmentPolicy::PERM_VIEW_ANY, WorkScheduleAssignment::class);

        $scheduleModel = WorkSchedule::query()->findOrFail($schedule);
        $items = $this->service->fetchBySchedule((int) $scheduleModel->id, (int) $scheduleModel->company_id, $request->validatedFilters());

        $data = collect($items->items())->map(static fn (WorkScheduleAssignment $a): array => [
            'id' => (int) $a->id,
            'company_id' => (int) $a->company_id,
            'work_schedule_id' => (int) $a->work_schedule_id,
            'work_shift_id' => (int) $a->work_shift_id,
            'work_shift_name' => (string) ($a->workShift?->name ?? ''),
            'employee_id' => (int) $a->employee_id,
            'employee_name' => trim((string) (($a->employee?->last_name ?? '').' '.($a->employee?->first_name ?? ''))),
            'day' => optional($a->day)->format('Y-m-d'),
            'start_time' => $a->start_time,
            'end_time' => $a->end_time,
            'created_at' => optional($a->created_at)->toDateTimeString(),
        ])->values()->all();

        return response()->json([
            'message' => 'Kiosztások sikeresen lekérve.',
            'data' => $data,
            'meta' => [
                'current_page' => $items->currentPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
                'last_page' => $items->lastPage(),
            ],
            'filter' => $request->validatedFilters(),
        ], Response::HTTP_OK);
    }

    /**
     * Új kiosztás létrehozása.
     *
     * @param StoreRequest $request Validált létrehozási kérés
     * @param int $schedule Route paraméter: work_schedule azonosító
     * @return JsonResponse HTTP 201 sikeres létrehozás esetén
     */
    public function store(StoreRequest $request, int $schedule): JsonResponse
    {
        $this->authorize(WorkScheduleAssignmentPolicy::PERM_CREATE, WorkScheduleAssignment::class);

        $scheduleModel = WorkSchedule::query()->findOrFail($schedule);
        if ((int) $scheduleModel->id !== (int) $request->assignmentPayload()['work_schedule_id']) {
            return response()->json(['message' => 'Érvénytelen beosztás.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $row = $this->service->store($request->assignmentPayload());

            return response()->json([
                'message' => 'Kiosztás sikeresen létrehozva.',
                'data' => ['id' => (int) $row->id],
            ], Response::HTTP_CREATED);
        } catch (QueryException $e) {
            return response()->json([
                'message' => 'A megadott dolgozóhoz erre a napra már létezik kiosztás ebben a beosztásban.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * Kiosztás frissítése.
     *
     * @param UpdateRequest $request Validált frissítési kérés
     * @param int $schedule Route paraméter: work_schedule azonosító
     * @param int $id Kiosztás azonosító
     * @return JsonResponse HTTP 200 sikeres frissítés esetén
     */
    public function update(UpdateRequest $request, int $schedule, int $id): JsonResponse
    {
        $this->authorize(WorkScheduleAssignmentPolicy::PERM_UPDATE, WorkScheduleAssignment::class);

        $scheduleModel = WorkSchedule::query()->findOrFail($schedule);

        try {
            $row = $this->service->update(
                $id,
                (int) $scheduleModel->id,
                (int) $scheduleModel->company_id,
                $request->assignmentPayload()
            );

            return response()->json([
                'message' => 'Kiosztás sikeresen frissítve.',
                'data' => ['id' => (int) $row->id],
            ], Response::HTTP_OK);
        } catch (QueryException $e) {
            return response()->json([
                'message' => 'A megadott dolgozóhoz erre a napra már létezik kiosztás ebben a beosztásban.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * Kiosztás törlése.
     *
     * @param DeleteRequest $request Validált törlési kérés
     * @param int $schedule Route paraméter: work_schedule azonosító
     * @param int $id Kiosztás azonosító
     * @return JsonResponse Törlés eredménye
     */
    public function destroy(DeleteRequest $request, int $schedule, int $id): JsonResponse
    {
        $this->authorize(WorkScheduleAssignmentPolicy::PERM_DELETE, WorkScheduleAssignment::class);

        $scheduleModel = WorkSchedule::query()->findOrFail($schedule);
        $deleted = $this->service->destroy($id, (int) $scheduleModel->id, (int) $scheduleModel->company_id);

        return response()->json([
            'message' => $deleted ? 'Kiosztás törölve.' : 'Törlés sikertelen.',
            'deleted' => $deleted,
        ], $deleted ? Response::HTTP_OK : Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    /**
     * Kiosztások tömeges törlése.
     *
     * @param BulkDeleteRequest $request Validált bulk törlési kérés
     * @param int $schedule Route paraméter: work_schedule azonosító
     * @return JsonResponse Törölt rekordok darabszáma
     */
    public function bulkDelete(BulkDeleteRequest $request, int $schedule): JsonResponse
    {
        $this->authorize(WorkScheduleAssignmentPolicy::PERM_BULK_DELETE, WorkScheduleAssignment::class);

        $scheduleModel = WorkSchedule::query()->findOrFail($schedule);
        $deleted = $this->service->bulkDelete($request->ids(), (int) $scheduleModel->id, (int) $scheduleModel->company_id);

        return response()->json([
            'message' => 'Tömeges törlés sikeres.',
            'deleted' => $deleted,
        ], Response::HTTP_OK);
    }
}
