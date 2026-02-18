<?php

namespace App\Http\Controllers;

use App\Http\Requests\Employee\BulkDeleteRequest;
use App\Http\Requests\Employee\IndexRequest;
use App\Http\Requests\Employee\StoreRequest;
use App\Http\Requests\Employee\UpdateRequest;
use App\Models\Employee;
use App\Policies\EmployeePolicy;
use App\Services\EmployeeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use App\Data\Employee\EmployeeData;
use App\Data\Employee\EmployeeIndexData;

/**
 * Munkavállaló controller osztály
 * 
 * HTTP kérések kezelése munkavállalók CRUD műveleteihez.
 * Inertia.js frontend integráció és JSON API végpontok.
 * Policy-alapú autorizációval.
 */
class EmployeeController extends Controller
{
    public function __construct(
            private readonly EmployeeService $service
    ) {}
    
    /**
     * Munkavállalók lista oldal megjelenítése
     * 
     * Inertia oldal renderelés szűrési paraméterekkel.
     * 
     * @param IndexRequest $request Validált kérés (search, company_id, field, order, per_page)
     * @return InertiaResponse Inertia válasz a HR/Employees/Index komponenssel
     */
    public function index(IndexRequest $request): InertiaResponse
    {
        $this->authorize(EmployeePolicy::PERM_VIEW_ANY, Employee::class);
        
        return Inertia::render('HR/Employees/Index', [
            'title'  => 'Dolgozók',
            'filter' => $request->validatedFilters(),
        ]);
    }
    
    /**
     * Munkavállalók listázása JSON formátumban
     * 
     * Lapozott lista meta adatokkal (current_page, per_page, total, last_page).
     * 
     * @param IndexRequest $request Validált kérés
     * @return JsonResponse Lapozott munkavállaló lista JSON-ben
     */
    public function fetch(IndexRequest $request): JsonResponse
    {
        $this->authorize(EmployeePolicy::PERM_VIEW_ANY, Employee::class);
        
        $employees = $this->service->fetch($request);
        
        $items = EmployeeIndexData::collect($employees->items());
        
        return response()->json([
            'message' => 'Dolgozók sikeresen lekérve.',
            'data' => $items,
            'meta' => [
                'current_page' => $employees->currentPage(),
                'per_page' => $employees->perPage(),
                'total' => $employees->total(),
                'last_page' => $employees->lastPage(),
            ],
            'filter' => $request->validatedFilters(),
        ], Response::HTTP_OK);
        
        /*
        $this->authorize(EmployeePolicy::PERM_VIEW_ANY, Employee::class);
        
        $employees = $this->service->fetch($request);

        $items = EmployeeIndexData::collect($employees->items());

        return response()->json([
            'message' => 'Dolgozók sikeresen lekérdezve',
            'data' => $items,
            'meta' => [
                'current_page' => $employees->currentPage(),
                'per_page'     => $employees->perPage(),
                'total'        => $employees->total(),
                'last_page'    => $employees->lastPage(),
            ],
            'filter' => $request->validatedFilters(),
        ], Response::HTTP_OK);
        */
    }
    
    /**
     * Egy munkavállaló lekérése azonosító alapján
     * 
     * @param int $id Munkavállaló azonosító
     * @return JsonResponse Munkavállaló adatok JSON-ben
     */
    public function getEmployee(int $id): JsonResponse
    {
        $employee = $this->service->getEmployee($id);
        $this->authorize(EmployeePolicy::PERM_VIEW, $employee);

        try {
            return response()->json(
                $employee,
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
     * Munkavállaló lekérése név alapján
     * 
     * @param string $name Munkavállaló keresztneve
     * @return JsonResponse Munkavállaló adatok JSON-ben
     */
    public function getEmployeeByName(string $name): JsonResponse
    {
        try {
            $employee = $this->service->getEmployeeByName($name);
            $this->authorize(EmployeePolicy::PERM_VIEW, $employee);

            return response()->json(
                $employee,
                Response::HTTP_OK
            );
        } catch(Throwable $th) {
            return response()->json(
                ['message' => 'Váratlan hiba történt'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
    
    public function store(EmployeeData $data): JsonResponse
    {
        $this->authorize(EmployeePolicy::PERM_CREATE, Employee::class);
        $created = $this->service->store($data);
        return response()->json([
            'message' => 'A dolgozó sikeresen létrehozva.',
            'data' => $created,
        ], Response::HTTP_CREATED);
    }
    
    public function update(int $id, EmployeeData $data): JsonResponse
    {
        $employee = $this->service->getEmployee($id);
        $this->authorize(EmployeePolicy::PERM_UPDATE, $employee);
        
        $updated = $this->service->update($data, $id);
        
        return response()->json([
            'message' => 'A dolgozó sikeresen frissítve.',
            'data' => $updated,
        ], Response::HTTP_OK);
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
        $this->authorize(EmployeePolicy::PERM_DELETE_ANY, Employee::class);
        
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
        try {
            $employee = $this->service->getEmployee($id);
            $this->authorize(EmployeePolicy::PERM_DELETE, $employee);
            
            $deleted = $this->service->destroy($id);

            return response()->json($deleted, Response::HTTP_OK);
        } catch(Throwable $th) {
            return response()->json([
                'message' => 'Törlés sikertelen.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /**
     * Munkavállalók lekérése select listához
     * 
     * Egyszerűsített lista (id, name) dropdown/select mezőkhöz.
     * 
     * @param Request $request HTTP kérés
     * @return array<int, array{id: int, name: string}> Munkavállalók tömbje
     */
    public function getToSelect(Request $request): array
    {
        return $this->service->getToSelect([]);
    }
}
