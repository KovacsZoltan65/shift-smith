<?php

namespace App\Http\Controllers;

use App\Http\Requests\Company\BulkDeleteRequest;
use App\Http\Requests\Company\IndexRequest;
use App\Http\Requests\Company\SelectorRequest;
use App\Models\Company;
use App\Models\User;
use App\Policies\CompanyPolicy;
use App\Services\CompanyService;
use Illuminate\Http\JsonResponse;
use App\Services\Selectors\CompanySelectorService;
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
        private readonly CompanyService $service,
        private readonly CompanySelectorService $companySelectorService,
    ) {}
    
    /**
     * Cégek listaoldal renderelése.
     */
    public function index(IndexRequest $request): InertiaResponse
    {
        $this->authorize(CompanyPolicy::PERM_VIEW_ANY, Company::class);

        return Inertia::render('Companies/Index', [
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
            'message' => __('companies.messages.fetch_success'),
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
     * @param int $id
     * @return JsonResponse
     */
    public function getCompany(int $id): JsonResponse
    {
        $company = $this->service->find($id);
        $this->authorize(CompanyPolicy::PERM_VIEW, $company);

        return response()->json([
            'message' => __('companies.messages.show_success'),
            'data' => CompanyData::fromModel($company),
        ], Response::HTTP_OK);
    }
    
    /**
     * Cég lekérése név alapján.
     * @param string $name
     * @return JsonResponse
     */
    public function getCompanyByName(string $name): JsonResponse
    {
        $company = $this->service->findByName($name);
        $this->authorize(CompanyPolicy::PERM_VIEW, $company);
        
        return response()->json([
            'message' => __('companies.messages.show_success'),
            'data' => CompanyData::fromModel($company),
        ], Response::HTTP_OK);
    }
    
    /**
     * Új cég létrehozása.
     * @param CompanyData $data
     * @return JsonResponse
     */
    public function store(CompanyData $data): JsonResponse
    {
        $this->authorize(CompanyPolicy::PERM_CREATE, Company::class);

        $created = $this->service->store($data);

        return response()->json([
            'message' => __('companies.messages.created_success'),
            'data' => $created,
        ], Response::HTTP_CREATED);
    }

    /**
     * Cég adatainak frissítése.
     * @param int $id
     * @param CompanyData $data
     * @return JsonResponse
     */
    public function update(int $id, CompanyData $data): JsonResponse
    {
        $company = $this->service->find($id);
        $this->authorize(CompanyPolicy::PERM_UPDATE, $company);

        $updated = $this->service->update($id, $data);

        return response()->json([
            'message' => __('companies.messages.updated_success'),
            'data' => $updated,
        ], Response::HTTP_OK);
    }
    
    /**
     * Több cég törlése egyszerre.
     * @param BulkDeleteRequest $request
     * @return JsonResponse
     */
    public function bulkDelete(BulkDeleteRequest $request): JsonResponse
    {
        $this->authorize(CompanyPolicy::PERM_DELETE_ANY, Company::class);
        
        $data = $request->validated();
        $deleted = $this->service->bulkDelete($data['ids']);

        return response()->json([
                'message' => __('companies.messages.delete_success'),
                'deleted' => $deleted,
            ], Response::HTTP_OK);
    }
    
    /**
     * Egy cég törlése.
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        $company = $this->service->find($id);
        $this->authorize(CompanyPolicy::PERM_DELETE, $company);
        
        $deleted = $this->service->destroy($id);

        return response()->json([
            'message' => $deleted
                ? __('companies.messages.delete_success')
                : __('companies.messages.delete_failed'),
            'deleted' => (bool) $deleted,
        ], $deleted ? Response::HTTP_OK : Response::HTTP_INTERNAL_SERVER_ERROR);
    }
    
    /**
     * Cégek lekérése select listához.
     * @param SelectorRequest $request
     * @return array<int, array{id:int, name:string}>
     */
    public function getToSelect(SelectorRequest $request): array
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return array_values(array_map(
            static fn (array $row): array => [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
            ],
            $this->companySelectorService->listSelectableCompaniesForUser($user)
        ));
    }
}
