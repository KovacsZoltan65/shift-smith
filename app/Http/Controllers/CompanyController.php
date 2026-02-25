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
 * HTTP végpontok kezelése a cégek CRUD műveleteihez.
 */
class CompanyController extends Controller
{
    public function __construct(
        private readonly CompanyService $service
    ) {}
    
    /**
     * Cégek listaoldal renderelése.
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
     * Cégek listázása JSON formátumban.
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
     * Cég lekérése azonosító alapján.
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
     * Cég lekérése név alapján.
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
     * Új cég létrehozása.
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
     * Több cég törlése egyszerre.
     */
    public function bulkDelete(BulkDeleteRequest $request): JsonResponse
    {
        $this->authorize(CompanyPolicy::PERM_DELETE_ANY, Company::class);
        
        $data = $request->validated();
        $deleted = $this->service->bulkDelete($data['ids']);

        return response()->json([
                'message' => 'Sikeres törlés.',
                'deleted' => $deleted,
            ], Response::HTTP_OK);
    }
    
    /**
     * Egy cég törlése.
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
     * Cégek lekérése select listához.
     *
     * @return array<int, array{id:int, name:string}>
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
