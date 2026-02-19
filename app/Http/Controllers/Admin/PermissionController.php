<?php

namespace App\Http\Controllers\Admin;

use App\Data\Permission\PermissionData;
use App\Data\Permission\PermissionIndexData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Permission\BulkDeleteRequest;
use App\Http\Requests\Permission\IndexRequest;
use App\Models\Admin\Permission;
use App\Policies\PermissionPolicy;
use App\Services\Admin\PermissionService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Jogosultság controller osztály
 *
 * HTTP kérések kezelése jogosultságok CRUD műveleteihez.
 * Inertia.js frontend integráció és JSON API végpontok.
 * Spatie Permission integráció.
 */
class PermissionController extends Controller
{
    /**
     * @param PermissionService $service Jogosultság service
     */
    public function __construct(
        private readonly PermissionService $service
    ) {}

    /**
     * Jogosultságok lista oldal megjelenítése.
     *
     * @param IndexRequest $request Validált kérés
     * @return InertiaResponse Inertia válasz az Admin/Permissions/Index komponenssel
     */
    public function index(IndexRequest $request): InertiaResponse
    {
        $this->authorize(PermissionPolicy::PERM_VIEW_ANY, Permission::class);

        return Inertia::render('Admin/Permissions/Index', [
            'title' => 'Jogosultságok',
            'filter' => $request->validatedFilters(),
        ]);
    }

    /**
     * Jogosultságok listázása JSON formátumban.
     *
     * @param IndexRequest $request Validált kérés
     * @return JsonResponse Lapozott jogosultság lista JSON-ben
     */
    public function fetch(IndexRequest $request): JsonResponse
    {
        $this->authorize(PermissionPolicy::PERM_VIEW_ANY, Permission::class);

        $permissions = $this->service->fetch($request);
        $items = PermissionIndexData::collect($permissions->items());

        return response()->json([
            'message' => 'Jogosultságok sikeresen lekérve.',
            'data' => $items,
            'meta' => [
                'current_page' => $permissions->currentPage(),
                'per_page' => $permissions->perPage(),
                'total' => $permissions->total(),
                'last_page' => $permissions->lastPage(),
            ],
            'filter' => $request->validatedFilters(),
        ], Response::HTTP_OK);
    }

    /**
     * Egy jogosultság lekérése azonosító alapján.
     *
     * @param int $id Jogosultság azonosító
     * @return JsonResponse Jogosultság adatok JSON-ben
     */
    public function getPermission(int $id): JsonResponse
    {
        $permission = $this->service->find($id);
        $this->authorize(PermissionPolicy::PERM_VIEW, $permission);

        return response()->json([
            'message' => 'Jogosultság sikeresen lekérve.',
            'data' => PermissionData::fromModel($permission),
        ], Response::HTTP_OK);
    }

    /**
     * Jogosultság lekérése név alapján.
     *
     * @param string $name Jogosultság neve
     * @return JsonResponse Jogosultság adatok JSON-ben
     */
    public function getPermissionByName(string $name): JsonResponse
    {
        $permission = $this->service->findByName($name);
        $this->authorize(PermissionPolicy::PERM_VIEW, $permission);

        return response()->json([
            'message' => 'Jogosultság sikeresen lekérve.',
            'data' => PermissionData::fromModel($permission),
        ], Response::HTTP_OK);
    }

    /**
     * Új jogosultság létrehozása.
     *
     * @param PermissionData $data Validált DTO adatok
     * @return JsonResponse Létrehozott jogosultság JSON-ben
     */
    public function store(PermissionData $data): JsonResponse
    {
        $this->authorize(PermissionPolicy::PERM_CREATE, Permission::class);

        $created = $this->service->store($data);

        return response()->json([
            'message' => 'A jogosultság sikeresen létrehozva.',
            'data' => $created,
        ], Response::HTTP_CREATED);
    }

    /**
     * Jogosultság adatainak frissítése.
     *
     * @param int $id Jogosultság azonosító
     * @param PermissionData $data Validált DTO adatok
     * @return JsonResponse Frissített jogosultság JSON-ben
     */
    public function update(int $id, PermissionData $data): JsonResponse
    {
        $permission = $this->service->find($id);
        $this->authorize(PermissionPolicy::PERM_UPDATE, $permission);

        $updated = $this->service->update($data, $id);

        return response()->json([
            'message' => 'Jogosultság sikeresen frissítve.',
            'data' => $updated,
        ], Response::HTTP_OK);
    }

    /**
     * Több jogosultság törlése egyszerre.
     *
     * @param BulkDeleteRequest $request Validált kérés
     * @return JsonResponse Törlés eredménye JSON-ben
     */
    public function bulkDelete(BulkDeleteRequest $request): JsonResponse
    {
        $this->authorize(PermissionPolicy::PERM_DELETE_ANY, Permission::class);

        $data = $request->validated();
        $deleted = $this->service->bulkDelete($data['ids']);

        return response()->json([
            'message' => 'Sikeres törlés.',
            'deleted' => $deleted,
        ], Response::HTTP_OK);
    }

    /**
     * Több jogosultság törlése egyszerre (backward compatible alias).
     *
     * @param BulkDeleteRequest $request Validált kérés
     * @return JsonResponse Törlés eredménye JSON-ben
     */
    public function destroyBulk(BulkDeleteRequest $request): JsonResponse
    {
        return $this->bulkDelete($request);
    }

    /**
     * Egy jogosultság törlése.
     *
     * @param int $id Jogosultság azonosító
     * @return JsonResponse Törlés eredménye JSON-ben
     */
    public function destroy(int $id): JsonResponse
    {
        $permission = $this->service->find($id);
        $this->authorize(PermissionPolicy::PERM_DELETE, $permission);
        $deleted = $this->service->destroy($id);

        return response()->json([
            'message' => $deleted ? 'Törlés sikeres.' : 'Törlés sikertelen.',
            'deleted' => (bool) $deleted,
        ], $deleted ? Response::HTTP_OK : Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    /**
     * Jogosultságok lekérése select listához.
     *
     * @return array<int, array{id: int, name: string}> Jogosultságok listája
     */
    public function getToSelect(): array
    {
        return $this->service->getToSelect();
    }
}
