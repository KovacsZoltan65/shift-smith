<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Role\BulkDeleteRequest;
use App\Http\Requests\Role\IndexRequest;
use App\Http\Requests\Role\StoreRequest;
use App\Http\Requests\Role\UpdateRequest;
use App\Models\Admin\Role;
use App\Services\Admin\RoleService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Szerepkör controller osztály
 * 
 * HTTP kérések kezelése szerepkörök CRUD műveleteihez.
 * Inertia.js frontend integráció és JSON API végpontok.
 * Spatie Permission integráció.
 */
class RoleController extends Controller
{
    /**
     * Constructor
     * 
     * @param RoleService $service Szerepkör service
     */
    public function __construct(
        private readonly RoleService $service
    ) {}
    
    /**
     * Szerepkörök lista oldal megjelenítése
     * 
     * @param IndexRequest $request Validált kérés
     * @return InertiaResponse Inertia válasz az Admin/Roles/Index komponenssel
     */
    public function index(IndexRequest $request): InertiaResponse
    {
        /**
         * A szerepkörök oldalát megjelenítéséhez kell hozzáférni.
         */
        $this->authorize('viewAny', Role::class);
        
        return Inertia::render('Admin/Roles/Index', [
            'title'  => 'Szerepkörök',
            'filter' => $request->validatedFilters(),
        ]);
    }

    /**
     * Szerepkörök listázása JSON formátumban
     * 
     * @param IndexRequest $request Validált kérés
     * @return JsonResponse Lapozott szerepkör lista JSON-ben
     */
    public function fetch(IndexRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Role::class);
        
        $roles = $this->service->fetch($request);
        
        return response()->json([
            'data' => $roles,
            'meta' => [
                'current_page' => $roles->currentPage(),
                'per_page' => $roles->perPage(),
                'total' => $roles->total(),
                'last_page' => $roles->lastPage(),
            ],
            'filter' => $request->validatedFilters(),
        ], Response::HTTP_OK);
    }
    
    /**
     * Egy szerepkör lekérése azonosító alapján
     * 
     * @param int $id Szerepkör azonosító
     * @return JsonResponse Szerepkör adatok JSON-ben
     * @throws Throwable
     */
    public function getRole(int $id): JsonResponse
    {
        $role = $this->service->getRole($id);
        $this->authorize('view', $role);
        
        try {
            // Normalizált payload (frontend barát)
            $role->loadMissing('permissions');

            return response()->json([
                'id' => (int) $role->id,
                'name' => (string) $role->name,
                'guard_name' => (string) $role->guard_name,
                'permission_ids' => $role->permissions->pluck('id')->map(fn ($id) => (int) $id)->all(),
                'created_at' => $role->created_at,
                'updated_at' => $role->updated_at,
            ], Response::HTTP_OK);
        } catch(Throwable $th) {
            return response()->json(
                ['message' => 'Váratlan hiba történt'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
    
    /**
     * Szerepkör lekérése név alapján
     * 
     * @param string $name Szerepkör neve
     * @return JsonResponse Szerepkör adatok JSON-ben
     * @throws Throwable
     */
    public function getRoleByName(string $name): JsonResponse
    {
        /** @var Role $role */
        $role = Role::where('name', '=', $name)->firstOrFail();
        $this->authorize('view', $role);
        
        try {
            $role->loadMissing('permissions');

            return response()->json([
                'id' => (int) $role->id,
                'name' => (string) $role->name,
                'guard_name' => (string) $role->guard_name,
                'permission_ids' => $role->permissions->pluck('id')->map(fn ($id) => (int) $id)->all(),
                'created_at' => $role->created_at,
                'updated_at' => $role->updated_at,
            ], Response::HTTP_OK);
        } catch(Throwable $th) {
            return response()->json(
                ['message' => 'Váratlan hiba történt'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Szerepkör lekérése név alapján (backward compatible alias)
     * 
     * @param string $name Szerepkör neve
     * @return JsonResponse Szerepkör adatok JSON-ben
     */
    public function byName(string $name): JsonResponse
    {
        return $this->getRoleByName($name);
    }
    
    /**
     * Új szerepkör létrehozása
     * 
     * @param StoreRequest $request Validált kérés
     * @return JsonResponse Létrehozott szerepkör JSON-ben
     * @throws \Throwable
     */
    public function store(StoreRequest $request): JsonResponse
    {
        $this->authorize('create', Role::class);
        
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
            $role = $this->service->store($data);
            
            return response()->json($role, Response::HTTP_OK);
        } catch(Throwable $th) {
            return response()->json(
                ['message' => 'Váratlan hiba történt'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
    
    /**
     * Szerepkör adatainak frissítése
     * 
     * @param UpdateRequest $request Validált kérés
     * @param int $id Szerepkör azonosító
     * @return JsonResponse Frissített szerepkör JSON-ben
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
            $role = $this->service->getRole($id);
            $this->authorize('update', $role);
            
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
     * Több szerepkör törlése egyszerre
     * 
     * @param BulkDeleteRequest $request Validált kérés
     * @return JsonResponse Törlés eredménye JSON-ben
     */
    public function bulkDelete(BulkDeleteRequest $request): JsonResponse
    {
        $this->authorize('deleteAny', Role::class);
        
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
     * Több szerepkör törlése egyszerre (backward compatible alias)
     * 
     * @param BulkDeleteRequest $request Validált kérés
     * @return JsonResponse Törlés eredménye JSON-ben
     */
    public function destroyBulk(BulkDeleteRequest $request): JsonResponse
    {
        return $this->bulkDelete($request);
    }
    
    /**
     * Egy szerepkör törlése
     * 
     * @param int $id Szerepkör azonosító
     * @return JsonResponse Törlés eredménye JSON-ben
     * @throws Throwable
     */
    public function destroy(int $id): JsonResponse
    {
        $role = $this->service->getRole($id);
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
     * Szerepkörök lekérése select listához
     * 
     * @return array<int, array{id: int, name: string}> Szerepkörök listája
     */
    public function getToSelect(): array
    {
        return $this->service->getToSelect();
    }
}
