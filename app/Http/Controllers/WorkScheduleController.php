<?php

namespace App\Http\Controllers;

use App\Http\Requests\WorkSchedule\BulkDeleteRequest;
use App\Http\Requests\WorkSchedule\IndexRequest;
use App\Http\Requests\WorkSchedule\StoreRequest;
use App\Http\Requests\WorkSchedule\UpdateRequest;
use App\Models\WorkSchedule;
use App\Services\WorkScheduleService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class WorkScheduleController extends Controller
{
    public function __construct(
        private readonly WorkScheduleService $service
    ) {}

    public function index(IndexRequest $request): InertiaResponse
    {
        $this->authorize('viewAny', WorkSchedule::class);

        return Inertia::render('WorkSchedules/Index', [
            'title'  => 'Beosztások',
            'filter' => $request->validatedFilters(),
        ]);
    }

    public function fetch(IndexRequest $request): JsonResponse
    {
        $this->authorize('viewAny', WorkSchedule::class);

        $workSchedules = $this->service->fetch($request);

        return response()->json([
            'data' => $workSchedules->items(),
            'meta' => [
                'current_page' => $workSchedules->currentPage(),
                'per_page'     => $workSchedules->perPage(),
                'total'        => $workSchedules->total(),
                'last_page'    => $workSchedules->lastPage(),
            ],
            'filter' => $request->validatedFilters(),
        ], Response::HTTP_OK);
    }

    public function getWorkSchedule(int $id): JsonResponse
    {
        $workSchedule = $this->service->getWorkSchedule($id);
        $this->authorize('view', $workSchedule);

        try {
            return response()->json($workSchedule, Response::HTTP_OK);
        } catch (Throwable $th) {
            return response()->json(['error' => $th->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store(StoreRequest $request): JsonResponse
    {
        $this->authorize('create', WorkSchedule::class);

        $data = $request->validated();

        try {
            $workSchedule = $this->service->store($data);

            return response()->json($workSchedule, Response::HTTP_OK);
        } catch (Throwable $th) {
            return response()->json(['message' => $th->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(UpdateRequest $request, int $id): JsonResponse
    {
        $data = $request->validated();

        try {
            $workSchedule = $this->service->getWorkSchedule($id);
            $this->authorize('update', $workSchedule);

            $updated = $this->service->update($data, $id);

            return response()->json($updated, Response::HTTP_OK);
        } catch (Throwable $th) {
            $code = $th instanceof \RuntimeException ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_INTERNAL_SERVER_ERROR;
            return response()->json(['message' => $th->getMessage()], $code);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        $workSchedule = $this->service->getWorkSchedule($id);
        $this->authorize('delete', $workSchedule);

        try {
            $deleted = $this->service->destroy($id);

            return response()->json($deleted, Response::HTTP_OK);
        } catch (Throwable $th) {
            $code = $th instanceof \RuntimeException ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_INTERNAL_SERVER_ERROR;
            return response()->json(['message' => $th->getMessage()], $code);
        }
    }

    public function bulkDelete(BulkDeleteRequest $request): JsonResponse
    {
        $this->authorize('deleteAny', WorkSchedule::class);

        $data = $request->validated();

        try {
            $deleted = $this->service->bulkDelete($data['ids']);

            return response()->json([
                'message' => 'Sikeres törlés.',
                'deleted' => $deleted,
            ], Response::HTTP_OK);
        } catch (Throwable $th) {
            $code = $th instanceof \RuntimeException ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_INTERNAL_SERVER_ERROR;

            return response()->json([
                'message' => $th->getMessage() ?: 'Törlés sikertelen.',
            ], $code);
        }
    }
}
