<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Permission\BulkDeleteRequest;
use App\Http\Requests\Permission\IndexRequest;
use App\Http\Requests\Permission\StoreRequest;
use App\Http\Requests\Permission\UpdateRequest;
use App\Models\Admin\Permission;
use App\Services\Admin\PermissionService;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Jogosultság controller osztály
 * 
 * HTTP kérések kezelése jogosultságok CRUD műveleteihez.
 * Inertia.js frontend integráció és JSON API végpontok.
 * Spatie Permission integráció cache flush-sal.
 */
class PermissionController extends Controller
{
    /**
     * Constructor
     * 
     * @param PermissionService $service Jogosultság service
     */
    public function __construct(
            private readonly PermissionService $service
    ) {}
    
    /**
     * Jogosultságok lista oldal megjelenítése
     * 
     * @param IndexRequest $request Validált kérés
     * @return InertiaResponse Inertia válasz az Admin/Permissions/Index komponenssel
     */
    public function index(IndexRequest $request): InertiaResponse
    {
        $this->authorize('viewAny', Permission::class);
        
        return Inertia::render('Admin/Permissions/Index', [
            'title'  => 'Jogosultságok',
            'filter' => $request->validatedFilters(),
        ]);
    }
    
    /**
     * Jogosultságok listázása JSON formátumban
     * 
     * @param IndexRequest $request Validált kérés
     * @return JsonResponse Lapozott jogosultság lista JSON-ben
     */
    public function fetch(IndexRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Permission::class);
        
        $permission = $this->service->fetch($request);
        
        return response()->json([
            'data' => $permission,
            'meta' => [
                'current_page' => $permission->currentPage(),
                'per_page' => $permission->perPage(),
                'total' => $permission->total(),
                'last_page' => $permission->lastPage(),
            ],
            'filter' => $request->validatedFilters(),
        ], Response::HTTP_OK);
    }
    
    /**
     * Egy jogosultság lekérése azonosító alapján
     * 
     * @param int $id Jogosultság azonosító
     * @return JsonResponse Jogosultság adatok JSON-ben
     * @throws Throwable
     */
    public function getPermission(int $id): JsonResponse
    {
        $permission = $this->service->getPermission($id);
        $this->authorize('view', $permission);
        
        try {
            return response()->json(
                $permission,
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
     * Jogosultság lekérése név alapján
     * 
     * @param string $name Jogosultság neve
     * @return JsonResponse Jogosultság adatok JSON-ben
     * @throws Throwable
     */
    public function getPermissionByName(string $name): JsonResponse
    {
        /** @var Permission $permission */
        $permission = Permission::where('name', '=', $name)->firstOrFail();
        $this->authorize('view', $permission);
        
        try {
            return response()->json(
                $permission,
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
     * Új jogosultság létrehozása
     * 
     * Cache flush a Spatie Permission registrar-ban.
     * 
     * @param StoreRequest $request Validált kérés
     * @return JsonResponse Létrehozott jogosultság JSON-ben
     * @throws \Throwable
     */
    public function store(StoreRequest $request): JsonResponse
    {
        $this->authorize('create', Permission::class);
        
        /**
         * A kérésből származó validált adatok.
         *
         * @var array{
         *   name: string,
         *   guard_name: string
         * } $data
         */
        $data = $request->validated();
        
        try {
            $permission = $this->service->store($data);
            
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            
            return response()->json($permission, Response::HTTP_OK);
        } catch(Throwable $th) {
            return response()->json(
                ['message' => 'Váratlan hiba történt'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
    
    /**
     * Jogosultság adatainak frissítése
     * 
     * @param UpdateRequest $request Validált kérés
     * @param int $id Jogosultság azonosító
     * @return JsonResponse Frissített jogosultság JSON-ben
     * @throws \Throwable
     */
    public function update(UpdateRequest $request, $id): JsonResponse
    {
        /**
         * @var array{
         *   name: string, 
         *   guard_name: string,
         * } $data
         */
        $data = $request->validated();
        
        try {
            $permission = $this->service->getPermission($id);
            $this->authorize('update', $permission);
            
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
     * Több jogosultság törlése egyszerre
     * 
     * Cache flush a Spatie Permission registrar-ban.
     * 
     * @param BulkDeleteRequest $request Validált kérés
     * @return JsonResponse Törlés eredménye JSON-ben
     */
    public function destroyBulk(BulkDeleteRequest $request): JsonResponse
    {
        $this->authorize('deleteAny', Permission::class);
        
        $data = $request->validated();
        
        try {
            $deleted = $this->service->destroyBulk($data['ids']);
            
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            
            return response()->json([
                'message' => 'Sikeres törlés.',
                'deleted' => $deleted,
            ], Response::HTTP_OK);
        } catch(Throwable $th) {
            return response()->json([
                'message' => 'Törlés sikertelen.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /**
     * Egy jogosultság törlése
     * 
     * @param int $id Jogosultság azonosító
     * @return JsonResponse Törlés eredménye JSON-ben
     */
    public function destroy(int $id): JsonResponse
    {
        $role = $this->service->getPermission($id);
        $this->authorize('delete', $role);
        
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
     * Jogosultságok lekérése select listához
     * 
     * @return array<int, array{id: int, name: string}> Jogosultságok listája
     */
    public function getToSelect(): array
    {
        return $this->service->getToSelect();
    }
}