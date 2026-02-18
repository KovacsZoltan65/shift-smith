<?php

namespace App\Http\Controllers;

use App\Http\Requests\WorkShift\BulkDeleteRequest;
use App\Http\Requests\WorkShift\IndexRequest;
use App\Http\Requests\WorkShift\StoreRequest;
use App\Http\Requests\WorkShift\UpdateRequest;
use App\Models\WorkShift;
use App\Policies\WorkShiftPolicy;
use App\Services\WorkShiftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Műszak controller osztály
 * 
 * HTTP kérések kezelése műszakok CRUD műveleteihez.
 * Inertia.js frontend integráció és JSON API végpontok.
 * Policy-alapú autorizációval.
 */
class WorkShiftController extends Controller
{
    /**
     * Constructor
     * 
     * @param WorkShiftService $service Műszak service
     */
    public function __construct(
        private readonly WorkShiftService $service
    ) {}
    
    /**
     * Műszakok lista oldal megjelenítése
     * 
     * @param IndexRequest $request Validált kérés
     * @return InertiaResponse Inertia válasz a WorkShifts/Index komponenssel
     */
    public function index(IndexRequest $request): InertiaResponse
    {
        $this->authorize(WorkShiftPolicy::PERM_VIEW_ANY, WorkShift::class);
        
        return Inertia::render('WorkShifts/Index', [
            'title'  => 'Műszakok',
            'filter' => $request->validatedFilters(),
        ]);
    }
    
    /**
     * Műszakok listázása JSON formátumban
     * 
     * @param IndexRequest $request Validált kérés
     * @return JsonResponse Lapozott műszak lista JSON-ben
     */
    public function fetch(IndexRequest $request): JsonResponse
    {
        $this->authorize(WorkShiftPolicy::PERM_VIEW_ANY, WorkShift::class);
        
        $work_shifts = $this->service->fetch($request);

        return response()->json([
            'data' => $work_shifts->items(),
            'meta' => [
                'current_page' => $work_shifts->currentPage(),
                'per_page'     => $work_shifts->perPage(),
                'total'        => $work_shifts->total(),
                'last_page'    => $work_shifts->lastPage(),
            ],
            'filter' => $request->validatedFilters(),
        ], Response::HTTP_OK);
    }
    
    /**
     * Egy műszak lekérése azonosító alapján
     * 
     * @param int $id Műszak azonosító
     * @return JsonResponse Műszak adatok JSON-ben
     */
    public function getWorkShift(int $id): JsonResponse
    {
        $work_shift = $this->service->getWorkShift($id);
        $this->authorize(WorkShiftPolicy::PERM_VIEW, $work_shift);

        try {
            return response()->json(
                $work_shift,
                Response::HTTP_OK
            );
        } catch(Throwable $th) {
            return response()->json(
                ['message' => 'Váratlan hiba történt'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
    
    /**
     * Műszak lekérése név alapján
     * 
     * @param string $name Műszak neve
     * @return JsonResponse Műszak adatok JSON-ben
     */
    public function getWorkShiftByName(string $name): JsonResponse
    {
        $work_shift = $this->service->getWorkShiftByName($name);
        $this->authorize(WorkShiftPolicy::PERM_VIEW, $work_shift);
        
        try {
            return response()->json(
                $work_shift,
                Response::HTTP_OK
            );
        } catch(Throwable $th) {
            return response()->json(
                ['message' => 'Váratlan hiba történt'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
    
    /**
     * Új műszak létrehozása
     * 
     * @param StoreRequest $request Validált kérés
     * @return JsonResponse Létrehozott műszak JSON-ben
     */
    public function store(StoreRequest $request): JsonResponse
    {
        $this->authorize(WorkShiftPolicy::PERM_CREATE, WorkShift::class);
        
        /**
         * @var array{
         *   company_id: int,
         *   name: string, 
         *   start_time: string,
         *   end_time: string,
         *   active: bool
         * } $data
         */
        $data = $request->validated();

        try {
            $work_shift = $this->service->store($data);

            return response()->json($work_shift, Response::HTTP_OK);
        } catch(Throwable $th) {
            return response()->json(
                ['message' => 'Váratlan hiba történt'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
    
    /**
     * Műszak adatainak frissítése
     * 
     * @param UpdateRequest $request Validált kérés
     * @param int $id Műszak azonosító
     * @return JsonResponse Frissített műszak JSON-ben
     * @throws \Throwable
     */
    public function update(UpdateRequest $request, $id): JsonResponse
    {
        /**
         * @var array{
         *   company_id: int,
         *   name: string,
         *   start_time: string,
         *   end_time: string,
         *   active: bool
         * } $data
         */
        $data = $request->validated();

        try {
            $work_shift = $this->service->getWorkShift($id);
            $this->authorize(WorkShiftPolicy::PERM_UPDATE, $work_shift);
        
            $updated = $this->service->update($data, $id);

            return response()->json($updated, Response::HTTP_OK);
        } catch(Throwable $th) {
            return response()->json(
                ['message' => 'Váratlan hiba történt'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
    
    /**
     * Több műszak törlése egyszerre
     * 
     * @param BulkDeleteRequest $request Validált kérés
     * @return JsonResponse Törlés eredménye JSON-ben
     * @throws \Throwable
     */
    public function bulkDelete(BulkDeleteRequest $request): JsonResponse
    {
        $this->authorize(WorkShiftPolicy::PERM_DELETE_ANY, WorkShift::class);
        
        $data = $request->validated();

        try {
            $deleted = $this->service->bulkDelete($data['ids']);
            
            return response()->json([
                'message' => 'Sikeres törlés.',
                'deleted' => $deleted,
            ], Response::HTTP_OK);
        } catch(Throwable $th) {
            return response()->json([
                'message' => 'Törlés sikertelen.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /**
     * Egy műszak törlése
     * 
     * @param int $id Műszak azonosító
     * @return JsonResponse Törlés eredménye JSON-ben
     * @throws \Throwable
     */
    public function destroy(int $id): JsonResponse
    {
        $work_shift = $this->service->getWorkShift($id);
        $this->authorize(WorkShiftPolicy::PERM_DELETE, $work_shift);
        
        try {
            $deleted = $this->service->destroy($id);

            return response()->json($deleted, Response::HTTP_OK);
        } catch(Throwable $th) {
            return response()->json([
                'message' => 'Törlés sikertelen.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /**
     * Műszakok lekérése select listához
     * 
     * Opcionális szűrés dolgozókkal rendelkező műszakokra.
     * 
     * @param Request $request HTTP kérés
     * @return array<int, array{id: int, name: string}> Műszakok listája
     */
    public function getToSelect(Request $request): array
    {
        $params = [];
        
        $onlyWithEmployees = $request->boolean('only_with_employees');
        
        if ($onlyWithEmployees) {
            $params['only_with_employees'] = true;
        }
        
        return $this->service->getToSelect($params);
    }
}
