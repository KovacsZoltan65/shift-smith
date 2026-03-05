<?php

namespace App\Http\Controllers;

use App\Http\Requests\Employee\BulkDeleteRequest;
use App\Http\Requests\Employee\EligibleSelectorRequest;
use App\Http\Requests\Employee\IndexRequest;
use App\Http\Requests\Employee\StoreRequest;
use App\Http\Requests\Employee\UpdateRequest;
use App\Models\Employee;
use App\Policies\EmployeePolicy;
use App\Services\EmployeeService;
use App\Services\EmployeeSupervisorService;
use App\Services\CurrentCompany;
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
    /**
     * @param EmployeeService $service Munkavállaló szolgáltatás
     */
    public function __construct(
            private readonly EmployeeService $service,
            private readonly CurrentCompany $currentCompany,
            private readonly EmployeeSupervisorService $employeeSupervisorService,
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

        $currentCompanyId = $this->currentCompany->currentCompanyId($request);
        abort_if($currentCompanyId === null, 403, 'No company selected');

        $filter = $request->validatedFilters();
        $filter['company_id'] = $currentCompanyId;
        
        return Inertia::render('HR/Employees/Index', [
            'title'  => 'Dolgozók',
            'filter' => $filter,
            'default_company_id' => $currentCompanyId,
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

        $currentCompanyId = $this->currentCompany->currentCompanyId($request);
        abort_if($currentCompanyId === null, 403, 'No company selected');
        $request->merge(['company_id' => $currentCompanyId]);
        
        $employees = $this->service->fetch($request);
        
        $items = EmployeeIndexData::collect($employees->items());
        
        $filter = $request->validatedFilters();
        $filter['company_id'] = $currentCompanyId;

        return response()->json([
            'message' => 'Dolgozók sikeresen lekérve.',
            'data' => $items,
            'meta' => [
                'current_page' => $employees->currentPage(),
                'per_page' => $employees->perPage(),
                'total' => $employees->total(),
                'last_page' => $employees->lastPage(),
            ],
            'filter' => $filter,
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
        $this->authorize('view', $employee);
        $history = $this->employeeSupervisorService->history((int) $employee->company_id, (int) $employee->id);

        try {
            return response()->json(
                [
                    ...$employee->toArray(),
                    'supervisor_history' => $history,
                ],
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
            $this->authorize('view', $employee);

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
     * Új munkavállaló létrehozása.
     *
     * @param EmployeeData $data Validált DTO adatok
     * @return JsonResponse Létrehozott munkavállaló JSON-ben
     */
    public function store(EmployeeData $data): JsonResponse
    {
        $this->authorize(EmployeePolicy::PERM_CREATE, Employee::class);
        $created = $this->service->store($data);
        return response()->json([
            'message' => 'A dolgozó sikeresen létrehozva.',
            'data' => $created,
        ], Response::HTTP_CREATED);
    }
    
    /**
     * Munkavállaló adatainak frissítése.
     *
     * @param int $id Munkavállaló azonosító
     * @param EmployeeData $data Validált DTO adatok
     * @return JsonResponse Frissített munkavállaló JSON-ben
     */
    public function update(int $id, EmployeeData $data): JsonResponse
    {
        $employee = $this->service->getEmployee($id);
        $this->authorize('update', $employee);
        
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
     * @return JsonResponse Törlés eredménye JSON-ben
     * @throws \Throwable
     */
    public function destroy(int $id): JsonResponse
    {
        $employee = $this->service->getEmployee($id);
        $this->authorize('delete', $employee);

        $deleted = $this->service->destroy($id);

        return response()->json($deleted, Response::HTTP_OK);
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
        $companyId = $request->input('company_id');
        $onlyActive = $request->boolean('only_active', true);

        return $this->service->getToSelect([
            'company_id' => ($companyId === null || $companyId === '') ? null : (int) $companyId,
            'only_active' => $onlyActive,
        ]);
    }

    /**
     * Egységes dolgozó selector endpoint.
     *
     * - `eligible_for_autoplan=1` esetén AutoPlan szűrt listát ad vissza.
     * - egyébként a hagyományos aktív dolgozó listát adja vissza.
     */
    public function selector(EligibleSelectorRequest $request): JsonResponse
    {
        $companyId = $this->currentCompany->currentCompanyId($request);
        abort_if($companyId === null, 403, 'No company selected');

        if (!$request->boolean('eligible_for_autoplan')) {
            $rows = $this->service->getToSelect([
                'company_id' => $companyId,
                'only_active' => true,
            ]);

            return response()->json([
                'message' => 'Dolgozó selector sikeresen lekérve.',
                'data' => $rows,
            ], Response::HTTP_OK);
        }

        $result = $this->service->getEligibleForAutoPlan($companyId, $this->eligibleParams($request));

        return response()->json([
            'message' => 'AutoPlan-re jogosult dolgozók sikeresen lekérve.',
            'data' => $result['data'],
            'meta' => $result['meta'],
        ], Response::HTTP_OK);
    }

    /**
     * Visszafelé kompatibilis külön végpont az AutoPlan selectorhoz.
     */
    public function getEligibleForAutoPlan(EligibleSelectorRequest $request): JsonResponse
    {
        $companyId = $this->currentCompany->currentCompanyId($request);
        abort_if($companyId === null, 403, 'No company selected');

        $result = $this->service->getEligibleForAutoPlan($companyId, $this->eligibleParams($request));

        return response()->json([
            'message' => 'AutoPlan-re jogosult dolgozók sikeresen lekérve.',
            'data' => $result['data'],
            'meta' => $result['meta'],
        ], Response::HTTP_OK);
    }

    /**
     * @return array{
     *   required_daily_minutes:int,
     *   month:string|null,
     *   date_from:string|null,
     *   date_to:string|null,
     *   search:string|null,
     *   shift_ids:list<int>,
     *   eligible_for_autoplan:bool
     * }
     */
    private function eligibleParams(EligibleSelectorRequest $request): array
    {
        $validated = $request->validated();

        // A request réteg már normalizálja a legacy kulcsokat.
        return [
            'required_daily_minutes' => isset($validated['required_daily_minutes']) ? (int) $validated['required_daily_minutes'] : 480,
            'month' => isset($validated['month']) ? (string) $validated['month'] : null,
            'date_from' => isset($validated['date_from']) ? (string) $validated['date_from'] : null,
            'date_to' => isset($validated['date_to']) ? (string) $validated['date_to'] : null,
            'search' => isset($validated['search']) ? trim((string) $validated['search']) : null,
            'shift_ids' => array_map('intval', (array) ($validated['shift_ids'] ?? [])),
            'eligible_for_autoplan' => $request->boolean('eligible_for_autoplan'),
        ];
    }
}
