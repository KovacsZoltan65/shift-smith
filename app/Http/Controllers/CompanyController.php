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
        
        return Inertia::render('Company/Index', [
            'title'  => 'Cégek',
            'filter' => $request->validatedFilters(),
        ]);
    }
    
    public function fetch(IndexRequest $request): LengthAwarePaginator
    {
        $this->authorize('viewAny', Company::class);
        
        return $this->service->fetch($request);
    }
    
    /**
     * @param int $id
     * @return \App\Models\Company
     */
    public function getCompany(int $id): Company
    {
        $this->authorize('view', Company::class);

        return $this->service->getCompany($id);
    }
    
    public function store(StoreRequest $request): JsonResponse
    {
        $this->authorize('create', Company::class);
        
        try {
            $company = $this->service->store($request->validated());
            
            return response()->json($company, Response::HTTP_OK);
        } catch(Throwable $th) {
            return response()->json(
                ['error' => $th->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
    
    public function update(UpdateRequest $request, $id): JsonResponse
    {
        $this->authorize('update', Company::class);
        
        try {
            $company = $this->service->update($request->validated(), $id);

            return response()->json($company, Response::HTTP_OK);
        } catch(Throwable $th) {
            return response()->json(
                ['error' => $th->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
    
    public function bulkDelete(array $ids): JsonResponse
    {
        $this->authorize('delete', Company::class);
        
        try {
            $deleted = $this->service->bulkDelete($ids);
            
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
    
    public function destroy(int $id): JsonResponse
    {
        $this->authorize('delete', Company::class);
        
        try {
            $deleted = $this->service->destroy($id);

            return response()->json($deleted, Response::HTTP_OK);
        } catch(Throwable $th) {
            return response()->json([
                'message' => 'Törlés sikertelen.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    public function getToSelect(): array
    {
        return $this->getToSelect();
    }
}
