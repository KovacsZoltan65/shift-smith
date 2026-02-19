<?php

namespace App\Http\Controllers;

use App\Data\WorkSchedule\WorkScheduleData;
use App\Data\WorkSchedule\WorkScheduleIndexData;
use App\Http\Requests\WorkSchedule\BulkDeleteRequest;
use App\Http\Requests\WorkSchedule\IndexRequest;
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
        $items = WorkScheduleIndexData::collect($workSchedules->items());

        return response()->json([
            'message' => 'Beosztások sikeresen lekérve.',
            'data' => $items,
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
        $workSchedule = $this->service->find($id);
        $this->authorize(WorkSchedulePolicy::PERM_VIEW, $workSchedule);

        return response()->json([
            'message' => 'Beosztás sikeresen lekérve.',
            'data' => WorkScheduleData::fromModel($workSchedule),
        ], Response::HTTP_OK);
    }

    /**
     * Új munkabeosztás létrehozása
     * 
     * @param WorkScheduleData $data Validált adat DTO
     * @return JsonResponse Létrehozott munkabeosztás JSON-ben
     */
    public function store(WorkScheduleData $data): JsonResponse
    {
        $this->authorize(WorkSchedulePolicy::PERM_CREATE, WorkSchedule::class);

        $created = $this->service->store($data);

        return response()->json([
            'message' => 'A beosztás sikeresen létrehozva.',
            'data' => $created,
        ], Response::HTTP_CREATED);
    }

    /**
     * Munkabeosztás adatainak frissítése
     * 
     * @param int $id Munkabeosztás azonosító
     * @param WorkScheduleData $data Validált adat DTO
     * @return JsonResponse Frissített munkabeosztás JSON-ben
     */
    public function update(int $id, WorkScheduleData $data): JsonResponse
    {
        $workSchedule = $this->service->find($id);
        $this->authorize(WorkSchedulePolicy::PERM_UPDATE, $workSchedule);

        $updated = $this->service->update($id, $data);

        return response()->json([
            'message' => 'Beosztás sikeresen frissítve.',
            'data' => $updated,
        ], Response::HTTP_OK);
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
            return response()->json(
                ['message' => 'Váratlan hiba történt'],
                $code
            );
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
                'message' => $code === Response::HTTP_UNPROCESSABLE_ENTITY ? ($th->getMessage() ?: 'Törlés sikertelen.') : 'Váratlan hiba történt',
            ], $code);
        }
    }
}
