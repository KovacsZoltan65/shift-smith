<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\WorkShiftAssignment\WorkShiftAssignmentData;
use App\Http\Requests\WorkShiftAssignment\DeleteRequest;
use App\Http\Requests\WorkShiftAssignment\ListRequest;
use App\Http\Requests\WorkShiftAssignment\StoreRequest;
use App\Services\WorkShiftAssignmentService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Műszakhoz rendelt dolgozók kezelése.
 */
class WorkShiftAssignmentController extends Controller
{
    public function __construct(
        private readonly WorkShiftAssignmentService $service
    ) {}

    /**
     * Hozzárendelések listázása műszak szerint.
     */
    public function index(ListRequest $request, int $work_shift): JsonResponse
    {
        $items = WorkShiftAssignmentData::collect($this->service->listByWorkShift($work_shift));

        return response()->json([
            'message' => 'Hozzárendelések sikeresen lekérve.',
            'data' => $items,
        ], Response::HTTP_OK);
    }

    /**
     * Hozzárendelhető munkabeosztások listázása műszak szerint.
     */
    public function schedules(ListRequest $request, int $work_shift): JsonResponse
    {
        return response()->json([
            'message' => 'Munkabeosztások sikeresen lekérve.',
            'data' => $this->service->getSchedulesForWorkShift($work_shift),
        ], Response::HTTP_OK);
    }

    /**
     * Dolgozó hozzárendelése műszakhoz.
     */
    public function store(StoreRequest $request, int $work_shift): JsonResponse
    {
        $assignment = $this->service->assign($work_shift, $request->validated());

        return response()->json([
            'message' => 'Dolgozó sikeresen hozzárendelve a műszakhoz.',
            'data' => WorkShiftAssignmentData::fromModel($assignment),
        ], Response::HTTP_CREATED);
    }

    /**
     * Hozzárendelés törlése.
     */
    public function destroy(DeleteRequest $request, int $work_shift, int $id): JsonResponse
    {
        $deleted = $this->service->unassign($work_shift, $id);

        return response()->json([
            'message' => $deleted ? 'Hozzárendelés törölve.' : 'Törlés sikertelen.',
            'deleted' => $deleted,
        ], $deleted ? Response::HTTP_OK : Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}
