<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\WorkShiftAssignment\DeleteRequest;
use App\Http\Requests\WorkShiftAssignment\ListRequest;
use App\Http\Requests\WorkShiftAssignment\StoreRequest;
use App\Models\WorkShift;
use App\Models\WorkShiftAssignment;
use App\Policies\WorkShiftAssigmentPolicy;
use App\Services\WorkShiftAssignmentService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Legacy/v1: Műszakhoz rendelt dolgozók kezelése.
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
        $this->authorize(WorkShiftAssigmentPolicy::PERM_VIEW_ANY, WorkShiftAssignment::class);

        $shift = WorkShift::query()->findOrFail($work_shift);
        $items = collect($this->service->listByShift((int) $shift->id, (int) $shift->company_id))
            ->map(fn (WorkShiftAssignment $a): array => [
                'id' => (int) $a->id,
                'employee_id' => (int) $a->employee_id,
                'employee_name' => trim((string) ($a->employee?->last_name . ' ' . $a->employee?->first_name)),
                'day' => optional($a->day)?->format('Y-m-d'),
                'active' => (bool) $a->active,
            ])
            ->values()
            ->all();

        return response()->json([
            'message' => 'Hozzárendelések sikeresen lekérve.',
            'data' => $items,
        ], Response::HTTP_OK);
    }

    /**
     * Dolgozó hozzárendelése műszakhoz.
     */
    public function store(StoreRequest $request, int $work_shift): JsonResponse
    {
        $this->authorize(WorkShiftAssigmentPolicy::PERM_CREATE, WorkShiftAssignment::class);

        $shift = WorkShift::query()->findOrFail($work_shift);
        $payload = $request->validated();
        $this->service->store([
            'company_id' => (int) $shift->company_id,
            'work_shift_id' => (int) $shift->id,
            'employee_id' => (int) $payload['employee_id'],
            'day' => (string) $payload['day'],
            'active' => (bool) ($payload['active'] ?? true),
        ]);

        return response()->json([
            'message' => 'Dolgozó sikeresen hozzárendelve a műszakhoz.',
        ], Response::HTTP_CREATED);
    }

    /**
     * Hozzárendelés törlése.
     */
    public function destroy(DeleteRequest $request, int $work_shift, int $id): JsonResponse
    {
        $this->authorize(WorkShiftAssigmentPolicy::PERM_DELETE, WorkShiftAssignment::class);

        $shift = WorkShift::query()->findOrFail($work_shift);
        $deleted = $this->service->destroy($id, (int) $shift->id, (int) $shift->company_id);

        return response()->json([
            'message' => $deleted ? 'Hozzárendelés törölve.' : 'Törlés sikertelen.',
            'deleted' => $deleted,
        ], $deleted ? Response::HTTP_OK : Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}
