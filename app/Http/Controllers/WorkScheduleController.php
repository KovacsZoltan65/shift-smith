<?php

namespace App\Http\Controllers;

use App\Data\WorkSchedule\WorkScheduleData;
use App\Data\WorkSchedule\WorkScheduleIndexData;
use App\Http\Requests\WorkSchedule\BulkDeleteRequest;
use App\Http\Requests\WorkSchedule\IndexRequest;
use App\Models\WorkSchedule;
use App\Policies\WorkSchedulePolicy;
use App\Services\CurrentCompany;
use App\Services\WorkScheduleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
        private readonly WorkScheduleService $service,
        private readonly CurrentCompany $currentCompany,
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
        $currentCompanyId = $this->getCurrentCompanyId($request);

        $filter = $request->validatedFilters();
        $filter['company_id'] = $currentCompanyId;

        return Inertia::render('WorkSchedules/Index', [
            'title'  => 'Beosztások',
            'filter' => $filter,
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
        $currentCompanyId = $this->getCurrentCompanyId($request);

        $workSchedules = $this->service->fetch($request, $currentCompanyId);
        $items = WorkScheduleIndexData::collect($workSchedules->items());
        $filter = $request->validatedFilters();
        $filter['company_id'] = $currentCompanyId;

        return response()->json([
            'message' => 'Beosztások sikeresen lekérve.',
            'data' => $items,
            'meta' => [
                'current_page' => $workSchedules->currentPage(),
                'per_page'     => $workSchedules->perPage(),
                'total'        => $workSchedules->total(),
                'last_page'    => $workSchedules->lastPage(),
            ],
            'filter' => $filter,
        ], Response::HTTP_OK);
    }

    /**
     * Egy munkabeosztás lekérése azonosító alapján
     * 
     * @param int $id Munkabeosztás azonosító
     * @return JsonResponse Munkabeosztás adatok JSON-ben
     */
    public function getWorkSchedule(Request $request, int $id): JsonResponse
    {
        $currentCompanyId = $this->getCurrentCompanyId($request);
        $workSchedule = $this->service->find($id, $currentCompanyId);
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
    public function store(Request $request, WorkScheduleData $data): JsonResponse
    {
        $this->authorize(WorkSchedulePolicy::PERM_CREATE, WorkSchedule::class);
        $currentCompanyId = $this->getCurrentCompanyId($request);

        $created = $this->service->store($data, $currentCompanyId);

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
    public function update(Request $request, int $id, WorkScheduleData $data): JsonResponse
    {
        $currentCompanyId = $this->getCurrentCompanyId($request);
        $workSchedule = $this->service->find($id, $currentCompanyId);
        $this->authorize(WorkSchedulePolicy::PERM_UPDATE, $workSchedule);

        $updated = $this->service->update($id, $data, $currentCompanyId);

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
    public function destroy(Request $request, int $id): JsonResponse
    {
        $currentCompanyId = $this->getCurrentCompanyId($request);
        $workSchedule = $this->service->getWorkSchedule($id, $currentCompanyId);
        $this->authorize(WorkSchedulePolicy::PERM_DELETE, $workSchedule);

        try {
            $deleted = $this->service->destroy($id, $currentCompanyId);

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
        $currentCompanyId = $this->getCurrentCompanyId($request);

        $data = $request->validated();

        try {
            $deleted = $this->service->bulkDelete($data['ids'], $currentCompanyId);

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

    private function getCurrentCompanyId(Request $request): int
    {
        $currentCompanyId = $this->currentCompany->currentCompanyId($request);
        abort_if($currentCompanyId === null, 403, 'No company selected');

        return $currentCompanyId;
    }
}
