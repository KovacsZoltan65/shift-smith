<?php

namespace App\Http\Controllers;

use App\Http\Requests\WorkSchedule\BulkDeleteRequest;
use App\Http\Requests\WorkSchedule\IndexRequest;
use App\Http\Requests\WorkSchedule\StoreRequest;
use App\Http\Requests\WorkSchedule\UpdateRequest;
use App\Models\WorkSchedule;
use App\Policies\WorkSchedulePolicy;
use App\Services\WorkScheduleService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Munkabeosztás controller osztály
 * 
 * HTTP kérések kezelése munkabeosztások CRUD műveleteihez.
 * Inertia.js frontend integráció és JSON API végpontok.
 * Policy-alapú autorizációval és publikálás védelem.
 */
class WorkScheduleController extends Controller
{
    /**
     * Constructor
     * 
     * @param WorkScheduleService $service Munkabeosztás service
     */
    public function __construct(
        private readonly WorkScheduleService $service
    ) {}

    /**
     * Munkabeosztások lista oldal megjelenítése
     * 
     * @param IndexRequest $request Validált kérés
     * @return InertiaResponse Inertia válasz a WorkSchedules/Index komponenssel
     */
    public function index(IndexRequest $request): InertiaResponse
    {
        $this->authorize(WorkSchedulePolicy::PERM_VIEW_ANY, WorkSchedule::class);

        return Inertia::render('WorkSchedules/Index', [
            'title'  => 'Beosztások',
            'filter' => $request->validatedFilters(),
        ]);
    }

    /**
     * Munkabeosztások listázása JSON formátumban
     * 
     * @param IndexRequest $request Validált kérés
     * @return JsonResponse Lapozott munkabeosztás lista JSON-ben
     */
    public function fetch(IndexRequest $request): JsonResponse
    {
        $this->authorize(WorkSchedulePolicy::PERM_VIEW_ANY, WorkSchedule::class);

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

    /**
     * Egy munkabeosztás lekérése azonosító alapján
     * 
     * @param int $id Munkabeosztás azonosító
     * @return JsonResponse Munkabeosztás adatok JSON-ben
     */
    public function getWorkSchedule(int $id): JsonResponse
    {
        $workSchedule = $this->service->getWorkSchedule($id);
        $this->authorize(WorkSchedulePolicy::PERM_VIEW, $workSchedule);

        try {
            return response()->json($workSchedule, Response::HTTP_OK);
        } catch (Throwable $th) {
            return response()->json(['error' => $th->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Új munkabeosztás létrehozása
     * 
     * @param StoreRequest $request Validált kérés
     * @return JsonResponse Létrehozott munkabeosztás JSON-ben
     */
    public function store(StoreRequest $request): JsonResponse
    {
        $this->authorize('create', WorkSchedule::class);

        /**
         * @var array{
         *   company_id: int,
         *   name: string,
         *   date_from: string,
         *   date_to: string,
         *   status: string,
         *   notes?: string|null
         * } $data
         */
        $data = $request->validated();

        try {
            $workSchedule = $this->service->store($data);

            return response()->json($workSchedule, Response::HTTP_OK);
        } catch (Throwable $th) {
            return response()->json(['message' => $th->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Munkabeosztás adatainak frissítése
     * 
     * @param UpdateRequest $request Validált kérés
     * @param int $id Munkabeosztás azonosító
     * @return JsonResponse Frissített munkabeosztás JSON-ben
     */
    public function update(UpdateRequest $request, int $id): JsonResponse
    {
        /**
         * @var array{
         *   company_id: int,
         *   name: string,
         *   date_from: string,
         *   date_to: string,
         *   status: string,
         *   notes?: string|null
         * } $data
         */
        $data = $request->validated();

        try {
            $workSchedule = $this->service->getWorkSchedule($id);
            $this->authorize(WorkSchedulePolicy::PERM_UPDATE, $workSchedule);

            $updated = $this->service->update($data, $id);

            return response()->json($updated, Response::HTTP_OK);
        } catch (Throwable $th) {
            $code = $th instanceof \RuntimeException ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_INTERNAL_SERVER_ERROR;
            return response()->json(['message' => $th->getMessage()], $code);
        }
    }

    /**
     * Egy munkabeosztás törlése
     * 
     * Publikált beosztások nem törölhetők.
     * 
     * @param int $id Munkabeosztás azonosító
     * @return JsonResponse Törlés eredménye JSON-ben
     */
    public function destroy(int $id): JsonResponse
    {
        $workSchedule = $this->service->getWorkSchedule($id);
        $this->authorize(WorkSchedulePolicy::PERM_DELETE, $workSchedule);

        try {
            $deleted = $this->service->destroy($id);

            return response()->json($deleted, Response::HTTP_OK);
        } catch (Throwable $th) {
            $code = $th instanceof \RuntimeException ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_INTERNAL_SERVER_ERROR;
            return response()->json(['message' => $th->getMessage()], $code);
        }
    }

    /**
     * Több munkabeosztás törlése egyszerre
     * 
     * Publikált beosztások nem törölhetők.
     * 
     * @param BulkDeleteRequest $request Validált kérés
     * @return JsonResponse Törlés eredménye JSON-ben
     */
    public function bulkDelete(BulkDeleteRequest $request): JsonResponse
    {
        $this->authorize(WorkSchedulePolicy::PERM_DELETE_ANY, WorkSchedule::class);

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
