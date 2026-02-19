<?php

namespace App\Http\Controllers;

use App\Http\Requests\Company\BulkDeleteRequest;
use App\Http\Requests\Company\IndexRequest;
use App\Models\Company;
use App\Policies\CompanyPolicy;
use App\Services\CompanyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;
use App\Data\Company\CompanyData;
use App\Data\Company\CompanyIndexData;

/**
 * Cég controller osztály
 * 
 * HTTP kérések kezelése cégek CRUD műveleteihez.
 * Inertia.js frontend integráció és JSON API végpontok.
 * Policy-alapú autorizációval.
 */
class CompanyController extends Controller
{
    /**
     * @param CompanyService $service Cég szolgáltatás
     */
    public function __construct(
        private readonly CompanyService $service
    ) {}
    
    /**
     * Cégek lista oldal megjelenítése
     * 
     * Inertia oldal renderelés szűrési paraméterekkel.
     * 
     * @param IndexRequest $request Validált kérés (search, field, order, per_page)
     * @return InertiaResponse Inertia válasz a Companies/Index komponenssel
     */
    public function index(IndexRequest $request): InertiaResponse
    {
        $this->authorize(CompanyPolicy::PERM_VIEW_ANY, Company::class);
        
        return Inertia::render('Companies/Index', [
            'title'  => 'Cégek',
            'filter' => $request->validatedFilters(),
        ]);
    }
    
    /**
     * Cégek listázása JSON formátumban
     * 
     * Lapozott lista meta adatokkal (current_page, per_page, total, last_page).
     * 
     * @param IndexRequest $request Validált kérés
     * @return JsonResponse Lapozott cég lista JSON-ben
     */
    public function fetch(IndexRequest $request): JsonResponse
    {
        $this->authorize(CompanyPolicy::PERM_VIEW_ANY, Company::class);
        
        $companies = $this->service->fetch($request);

        $items = CompanyIndexData::collect($companies->items());

        return response()->json([
            'message' => 'Cégek sikeresen lekérve.',
            'data' => $items,
            'meta' => [
                'current_page' => $companies->currentPage(),
                'per_page'     => $companies->perPage(),
                'total'        => $companies->total(),
                'last_page'    => $companies->lastPage(),
            ],
            'filter' => $request->validatedFilters(),
        ], Response::HTTP_OK);
    }
    
    /**
     * Egy cég lekérése azonosító alapján
     * 
     * @param int $id Cég azonosító
     * @return JsonResponse Cég adatok JSON-ben
     */
    public function getCompany(int $id): JsonResponse
    {
        $company = $this->service->find($id);
        $this->authorize(CompanyPolicy::PERM_VIEW, $company);

        return response()->json([
            'message' => 'Cég sikeresen lekérve.',
            'data' => CompanyData::fromModel($company),
        ], Response::HTTP_OK);
    }
    
    /**
     * Cég lekérése név alapján
     * 
     * @param string $name Cég neve
     * @return JsonResponse Cég adatok JSON-ben
     */
    public function getCompanyByName(string $name): JsonResponse
    {
        $company = $this->service->findByName($name);
        $this->authorize(CompanyPolicy::PERM_VIEW, $company);
        
        return response()->json([
            'message' => 'Cég sikeresen lekérve.',
            'data' => CompanyData::fromModel($company),
        ], Response::HTTP_OK);
    }
    
    /**
     * Új cég létrehozása
     * 
     * Validált adatokkal új cég létrehozása.
     * 
     * @param CompanyData $data Validált DTO adatok
     * @return JsonResponse Létrehozott cég JSON-ben
     */
    public function store(CompanyData $data): JsonResponse
    {
        $this->authorize(CompanyPolicy::PERM_CREATE, Company::class);

        $created = $this->service->store($data);

        return response()->json([
            'message' => 'A cég sikeresen létrehozva.',
            'data' => $created,
        ], Response::HTTP_CREATED);
    }

    /**
     * Cég adatainak frissítése.
     *
     * @param int $id Cég azonosító
     * @param CompanyData $data Validált DTO adatok
     * @return JsonResponse Frissített cég JSON-ben
     */
    public function update(int $id, CompanyData $data): JsonResponse
    {
        $company = $this->service->find($id);
        $this->authorize(CompanyPolicy::PERM_UPDATE, $company);

        $updated = $this->service->update($id, $data);

        return response()->json([
            'message' => 'Cég sikeresen frissítve.',
            'data' => $updated,
        ], Response::HTTP_OK);
    }
    
    /**
     * Több rekord törlése egyszerre.
     *
     * Engedélyezés: 'delete' policy.
     * Validálás: BulkDeleteRequest.
     *
     * @param  \App\Http\Requests\Company\BulkDeleteRequest  $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Throwable
     */
    public function bulkDelete(BulkDeleteRequest $request): JsonResponse
    {
        $this->authorize(CompanyPolicy::PERM_DELETE_ANY, Company::class);
        
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
        $company = $this->service->find($id);
        $this->authorize(CompanyPolicy::PERM_DELETE, $company);
        
        $deleted = $this->service->destroy($id);

        return response()->json([
            'message' => $deleted ? 'Törlés sikeres.' : 'Törlés sikertelen.',
            'deleted' => (bool) $deleted,
        ], $deleted ? Response::HTTP_OK : Response::HTTP_INTERNAL_SERVER_ERROR);
    }
    
    /**
     * Cégek lekérése select listához
     * 
     * Egyszerűsített lista (id, name) dropdown/select mezőkhöz.
     * Opcionálisan csak olyan cégek, amelyeknek van munkavállalója.
     * 
     * @param Request $request HTTP kérés (only_with_employees paraméterrel)
     * @return array<int, array{id: int, name: string}> Cégek tömbje
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
