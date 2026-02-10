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
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class PermissionController extends Controller
{
    public function __construct(
            private readonly PermissionService $service
    ) {}
    
    /**
     * Index oldal betöltése.
     *
     * Ez a metódus adatokat egy InertiaResponse objektet, amely tartalmazza a jogosultságok
     * oldalát, és a meta adatokat, amely tartalmazza a jogosultságok számát, az oldal számát,
     * a lapok számát, és az utolsó oldal számát.
     *
     * @param IndexRequest $request A lekérendő jogosultságok azonosítója.
     * @return InertiaResponse A jogosultságok oldala egy InertiaResponse objektumban.
     */
    public function index(IndexRequest $request): InertiaResponse
    {
        $this->authorize('viewAny', Permission::class);
        
        return Inertia::render('Permissions/Index', [
            'title'  => 'Jogosultságok',
            'filter' => $request->validatedFilters(),
        ]);
    }
    
    /**
     * Adatok az index oldalhoz.
     *
     * Ez a metódus adatokat szolgáltat az index oldalhoz.
     * A metódus egy szabály listát ad vissza, amely tartalmazza a szabályok adata mezőjét, és a meta adatokat,
     * amely tartalmazza a szabályok számát, az oldal számát, a lapok számát, és az utolsó oldal számát.
     * A metódus a szerepkörök listáját egy JsonResponse objektumban adja vissza.
     *
     * @param IndexRequest $request A lekérendő szabályok azonosítója.
     * @return JsonResponse A szabályok listája egy JsonResponse objektumban.
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
    
    /*
     * Szabály lekérése az azonosítója alapján.
     * Ez a metódus egy szabályt kér le az azonosítója alapján.
     * Először lekéri a szabályt az adatbázisból, majd ellenőrzi, hogy a felhasználó jogosult-e megtekinteni azt.
     * Ha a felhasználó jogosult, akkor a szabályt JSON formátumban adja vissza.
     * Ha a felhasználó nem jogosult, akkor egy 500 Internal Server Error üzenetet ad vissza.
     * @param int $id A lekérendő szabály azonosítója.
     * @return JsonResponse A szerepkör JSON formátumban, vagy egy 500 Internal Server Error válasz.
     * @throws Throwable Ha hiba történik a szabály lekérése során.
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
                ['error' => $th->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
    
    /**
     * Név alapján kér le egy szerepkört.
     *
     * Ez a metódus név alapján kér le egy szerepkört az adatbázisból.
     * Kivételt dob, ha a szerepkör nem létezik.
     *
     * @param string $name A lekérendő szabály neve.
     * @return JsonResponse A szabály JSON válaszként.
     * @throws Throwable Ha a szabály nem létezik.
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
                ['error' => $th->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
    
    /**
     * Létrehoz egy új szerepkört.
     *
     * Ez a vezérlőmetódus új szerepkört hoz létre a megadott adatokkal.
     * Érvényesíti a bejövő kérést, majd meghívja a szolgáltatás metódusát a szerepkör
     * tárolására.
     *
     * @param StoreRequest $request
     * @return JsonResponse Egy JSON válasz a létrehozott szabályokkal.
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
            
            return response()->json($permission, Response::HTTP_OK);
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
     * @param  UpdateRequest  $request
     * @param  int  $id  A módosítandó rekord azonosítója.
     * @return JsonResponse  A frissített rekord adatait tartalmazó JSON válasz.
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
                ['error' => $th->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
    
    public function bulkDelete(BulkDeleteRequest $request): JsonResponse
    {
        $this->authorize('deleteAny', Permission::class);
        
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
     * Rekordokat tartalmazó többi lista lekérdezéséhez.
     *
     * A lista a következ  összetevőket tartalmazza:
     * - id: azonosító
     * - name: a rekord neve
     *
     * @return array<int, array{id: int, name: string}> A rekordokat tartalmazó többi lista.
     */
    public function getToSelect(): array
    {
        return $this->service->getToSelect();
    }
}