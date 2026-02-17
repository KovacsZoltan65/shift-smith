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

class WorkShiftController extends Controller
{
    public function __construct(
        private readonly WorkShiftService $service
    ) {}
    
    public function index(IndexRequest $request): InertiaResponse
    {
        $this->authorize(WorkShiftPolicy::PERM_VIEW_ANY, WorkShift::class);
        
        return Inertia::render('WorkShifts/Index', [
            'title'  => 'Műszakok',
            'filter' => $request->validatedFilters(),
        ]);
    }
    
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
     * @param int $id
     * @return \Illuminate\Http\JsonResponse  A cég adatait tartalmazó JSON válasz.
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
                ['error' => $th->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
    
    /**
     * 
     * @param string $name
     * @return JsonResponse
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
                ['error' => $th->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
    
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
                ['error' => $th->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
    
    /**
     * Meglévő rekord adatainak frissítése.
     *
     * Engedélyezés: 'update' policy.
     *
     * @param  \App\Http\Requests\WorkShift\UpdateRequest  $request
     * @param  int  $id  A módosítandó rekord azonosítója.
     * @return \Illuminate\Http\JsonResponse  A frissített rekord adatait tartalmazó JSON válasz.
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
                ['error' => $th->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
    
    /**
     * Több rekord törlése egyszerre.
     *
     * Engedélyezés: 'delete' policy.
     * Validálás: BulkDeleteRequest.
     *
     * @param  \App\Http\Requests\WorkShift\BulkDeleteRequest  $request
     * @return \Illuminate\Http\JsonResponse
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
     * Egyetlen rekord törlése.
     *
     * Engedélyezés: 'delete' policy.
     *
     * @param  int  $id  A törlendő rekord azonosítója.
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
     * Summary of getToSelect
     * @return array<int, array{id: int, name: string}>
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
