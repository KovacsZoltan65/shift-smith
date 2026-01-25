<?php

namespace App\Http\Controllers;

use App\Http\Requests\Company\BulkDeleteRequest;
use App\Http\Requests\Company\DeleteRequest;
use App\Http\Requests\Company\IndexRequest;
use App\Http\Requests\Company\StoreRequest;
use App\Http\Requests\Company\UpdateRequest;
use App\Models\Company;
use App\Services\CompanyService;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class CompanyController extends Controller
{
    public function __construct(
        private readonly CompanyService $service
    ) {
        // Ha használsz Policy-t:
        // $this->authorizeResource(User::class, 'user');
    }
    
    public function index(IndexRequest $request): InertiaResponse
    {
        $this->authorize('viewAny', Company::class);
        
        return Inertia::render('Companies/Index', [
            'title'  => 'Cégek',
            'filter' => $request->validatedFilters(),
        ]);
    }
    
    public function fetch(IndexRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Company::class);
        
        $companies = $this->service->fetch($request);

        return response()->json([
            'data' => $companies->items(),
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
     * @param int $id
     * @return \Illuminate\Http\JsonResponse  A cég adatait tartalmazó JSON válasz.
     */
    public function getCompany(int $id): JsonResponse
    {
        //$this->authorize('view', Company::class);
        
        $company = $this->service->getCompany($id);
        $this->authorize('view', $company);

        try {
            
            
            return response()->json(
                $company,
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
        $this->authorize('create', Company::class);
        
        /**
         * @var array{
         *   name: string, 
         *   email: string,
         *   address: string,
         *   phone: string,
         *   active: bool
         * } $data
         */
        $data = $request->validated();

        try {
            $company = $this->service->store($data);

            return response()->json($company, Response::HTTP_OK);
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
     * @param  \App\Http\Requests\Company\UpdateRequest  $request
     * @param  int  $id  A módosítandó rekord azonosítója.
     * @return \Illuminate\Http\JsonResponse  A frissített rekord adatait tartalmazó JSON válasz.
     * @throws \Throwable
     */
    public function update(UpdateRequest $request, $id): JsonResponse
    {
        //$this->authorize('update', Company::class);
        
        /**
         * @var array{
         *   name: string, 
         *   email: string,
         *   address: string,
         *   phone: string,
         *   active: bool
         * } $data
         */
        $data = $request->validated();

        try {
            $company = $this->service->getCompany($id);
            $this->authorize('update', $company);
        
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
     * @param  \App\Http\Requests\Company\BulkDeleteRequest  $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Throwable
     */
    public function bulkDelete(BulkDeleteRequest $request): JsonResponse
    {
        $this->authorize('deleteAny', Company::class);
        
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
        $company = $this->service->getCompany($id);
        $this->authorize('delete', $company);
        
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
