<?php

namespace App\Http\Controllers\Admin;

use App\Data\Role\RoleData;
use App\Data\Role\RoleIndexData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Role\BulkDeleteRequest;
use App\Http\Requests\Role\IndexRequest;
use App\Models\Admin\Role;
use App\Policies\RolePolicy;
use App\Services\Admin\RoleService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

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
        $this->authorize(RolePolicy::PERM_VIEW_ANY, Role::class);
        
        return Inertia::render('Admin/Roles/Index', [
            'title'  => __('roles.title'),
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
        $this->authorize(RolePolicy::PERM_VIEW_ANY, Role::class);
        
        $roles = $this->service->fetch($request);
        $items = RoleIndexData::collect($roles->items());
        
        return response()->json([
            'message' => __('roles.messages.fetch_success'),
            'data' => $items,
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
     */
    public function getRole(int $id): JsonResponse
    {
        $role = $this->service->find($id);
        $this->authorize(RolePolicy::PERM_VIEW, $role);

        return response()->json([
            'message' => __('roles.messages.show_success'),
            'data' => RoleData::fromModel($role),
        ], Response::HTTP_OK);
    }
    
    /**
     * Szerepkör lekérése név alapján
     * 
     * @param string $name Szerepkör neve
     * @return JsonResponse Szerepkör adatok JSON-ben
     */
    public function getRoleByName(string $name): JsonResponse
    {
        $role = $this->service->findByName($name);
        $this->authorize(RolePolicy::PERM_VIEW, $role);

        return response()->json([
            'message' => __('roles.messages.show_success'),
            'data' => RoleData::fromModel($role),
        ], Response::HTTP_OK);
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
     * @param RoleData $data Validált DTO adatok
     * @return JsonResponse Létrehozott szerepkör JSON-ben
     */
    public function store(RoleData $data): JsonResponse
    {
        $this->authorize(RolePolicy::PERM_CREATE, Role::class);

        $created = $this->service->store($data);

        return response()->json([
            'message' => __('roles.messages.created_success'),
            'data' => $created,
        ], Response::HTTP_CREATED);
    }
    
    /**
     * Szerepkör adatainak frissítése
     * 
     * @param int $id Szerepkör azonosító
     * @param RoleData $data Validált DTO adatok
     * @return JsonResponse Frissített szerepkör JSON-ben
     */
    public function update(int $id, RoleData $data): JsonResponse
    {
        $role = $this->service->find($id);
        $this->authorize(RolePolicy::PERM_UPDATE, $role);

        $updated = $this->service->update($data, $id);

        return response()->json([
            'message' => __('roles.messages.updated_success'),
            'data' => $updated,
        ], Response::HTTP_OK);
    }
    
    /**
     * Több szerepkör törlése egyszerre
     * 
     * @param BulkDeleteRequest $request Validált kérés
     * @return JsonResponse Törlés eredménye JSON-ben
     */
    public function bulkDelete(BulkDeleteRequest $request): JsonResponse
    {
        $this->authorize(RolePolicy::PERM_DELETE_ANY, Role::class);
        
        $data = $request->validated();
        $deleted = $this->service->bulkDelete($data['ids']);

        return response()->json([
            'message' => __('common.delete_success'),
            'deleted' => $deleted,
        ], Response::HTTP_OK);
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
     */
    public function destroy(int $id): JsonResponse
    {
        $role = $this->service->find($id);
        $this->authorize(RolePolicy::PERM_DELETE, $role);
        $deleted = $this->service->destroy($id);

        return response()->json([
            'message' => $deleted ? __('roles.messages.deleted_success') : __('common.delete_failed'),
            'deleted' => (bool) $deleted,
        ], $deleted ? Response::HTTP_OK : Response::HTTP_INTERNAL_SERVER_ERROR);
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
