<?php

namespace App\Http\Controllers;

use App\Http\Requests\Employee\BulkDeleteRequest;
use App\Http\Requests\Employee\DeleteRequest;
use App\Http\Requests\Employee\IndexRequest;
use App\Http\Requests\Employee\StoreRequest;
use App\Http\Requests\Employee\UpdateRequest;
use App\Models\Employee;
use App\Services\EmployeeService;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class EmployeeController extends Controller
{
    public function __construct(
            private readonly EmployeeService $service
    ) {
        //
    }
    
    public function index(IndexRequest $request): InertiaResponse
    {
        $this->authorize('viewAny', Employee::class);
        
        return Inertia::render('HR/Employees/Index', [
            'title'  => 'Dolgozók',
            'filter' => $request->validatedFilters(),
        ]);
    }
    
    public function fetch(IndexRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Employee::class);
        
        $employee = $this->service->fetch($request);

        return response()->json([
            'data' => $employee->items(),
            'meta' => [
                'current_page' => $employee->currentPage(),
                'per_page'     => $employee->perPage(),
                'total'        => $employee->total(),
                'last_page'    => $employee->lastPage(),
            ],
            'filter' => $request->validatedFilters(),
        ], Response::HTTP_OK);
    }
    
    /**
     * @param int $id
     * @return JsonResponse  A dolgozó adatait tartalmazó JSON válasz.
     */
    public function getEmployee(int $id): JsonResponse
    {
        $employee = $this->service->getEmployee($id);
        $this->authorize('view', $employee);

        try {
            return response()->json(
                $employee,
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
    public function getEmployeeByName(string $name): JsonResponse
    {
        $employee = $this->service->getEmployeeByName($name);
        $this->authorize('view', $employee);
        
        try {
            return response()->json(
                $employee,
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
        $this->authorize('create', Employee::class);
        
        /**
         * @var array{
         *   company_id: int
         *   first_name: string, 
         *   last_name: string,
         *   email: string,
         *   address: string,
         *   phone: string,
         *   hired_at: string,
         *   active: bool
         * } $data
         */
        $data = $request->validated();
        
        try {
            $employee = $this->service->store($data);

            return response()->json($employee, Response::HTTP_OK);
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
     * @param  \App\Http\Requests\Employee\UpdateRequest  $request
     * @param  int  $id  A módosítandó rekord azonosítója.
     * @return JsonResponse  A frissített rekord adatait tartalmazó JSON válasz.
     * @throws \Throwable
     */
    public function update(UpdateRequest $request, $id): JsonResponse
    {
        //$this->authorize('update', Company::class);
        
        /**
         * @var array{
         *   first_name: string, 
         *   last_name: string, 
         *   email: string,
         *   address: string,
         *   phone: string,
         *   hired_at: string,
         *   active: bool
         * } $data
         */
        $data = $request->validated();

        try {
            $employee = $this->service->getEmployee($id);
            $this->authorize('update', $employee);
        
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
     * @param  \App\Http\Requests\Employee\BulkDeleteRequest  $request
     * @return JsonResponse
     * @throws \Throwable
     */
    public function bulkDelete(BulkDeleteRequest $request): JsonResponse
    {
        $this->authorize('deleteAny', Employee::class);
        
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
     * @param  int  $id  A törlendo rekord azonosítója.
     * @throws \Throwable
     */
    public function destroy(int $id): JsonResponse
    {
        //$this->authorize('delete', Company::class);
        $employee = $this->service->getEmployee($id);
        $this->authorize('delete', $employee);
        
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
    public function getToSelect(): array
    {
        return $this->service->getToSelect();
    }
}
