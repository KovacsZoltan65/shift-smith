<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\WorkShiftAssignment\DeleteRequest;
use App\Http\Requests\WorkShiftAssignment\ListRequest;
use App\Http\Requests\WorkShiftAssignment\StoreRequest;
use App\Models\WorkShift;
use App\Models\WorkShiftAssignment;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Műszakhoz rendelt dolgozók kezelése.
 */
class WorkShiftAssignmentController extends Controller
{
    /**
     * Hozzárendelések listázása műszak szerint.
     */
    public function index(ListRequest $request, int $work_shift): JsonResponse
    {
        $shift = WorkShift::query()->findOrFail($work_shift);

        $items = WorkShiftAssignment::query()
            ->with('employee:id,first_name,last_name')
            ->where('work_shift_id', $shift->id)
            ->orderByDesc('day')
            ->get()
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
        $shift = WorkShift::query()->findOrFail($work_shift);
        $payload = $request->validated();

        $assignment = WorkShiftAssignment::query()
            ->withTrashed()
            ->firstOrNew([
                'company_id' => (int) $shift->company_id,
                'employee_id' => (int) $payload['employee_id'],
                'day' => (string) $payload['day'],
            ]);

        $assignment->fill([
            'company_id' => (int) $shift->company_id,
            'work_shift_id' => (int) $shift->id,
            'employee_id' => (int) $payload['employee_id'],
            'day' => (string) $payload['day'],
            'active' => (bool) ($payload['active'] ?? true),
        ]);
        $assignment->save();

        if ($assignment->trashed()) {
            $assignment->restore();
        }

        return response()->json([
            'message' => 'Dolgozó sikeresen hozzárendelve a műszakhoz.',
        ], Response::HTTP_CREATED);
    }

    /**
     * Hozzárendelés törlése.
     */
    public function destroy(DeleteRequest $request, int $work_shift, int $id): JsonResponse
    {
        WorkShift::query()->findOrFail($work_shift);

        $assignment = WorkShiftAssignment::query()
            ->where('work_shift_id', $work_shift)
            ->findOrFail($id);

        $deleted = (bool) $assignment->delete();

        return response()->json([
            'message' => $deleted ? 'Hozzárendelés törölve.' : 'Törlés sikertelen.',
            'deleted' => $deleted,
        ], $deleted ? Response::HTTP_OK : Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}
