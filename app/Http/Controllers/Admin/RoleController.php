<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Role\IndexRequest;
use App\Http\Requests\Role\StoreRequest;
use App\Http\Requests\Role\UpdateRequest;
use App\Models\Role;
use App\Services\RoleService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class RoleController extends Controller
{
    public function __construct(
        private readonly RoleService $service
    ) {}
    
    /**
     * Index oldal betöltése.
     *
     * Ez a metódus adatokat egy InertiaResponse objektet, amely tartalmazza a szerepkörök
     * oldalát, és a meta adatokat, amely tartalmazza a szerepkörök számát, az oldal számát,
     * a lapok számát, és az utolsó oldal számát.
     *
     * @param IndexRequest $request A lekérendő szerepkörök azonosítója.
     * @return InertiaResponse A szerepkörök oldala egy InertiaResponse objektumban.
     */
    public function index(IndexRequest $request): InertiaResponse
    {
        /**
         * A szerepkörök oldalát megjelenítéséhez kell hozzáférni.
         */
        $this->authorize('viewAny', Role::class);
        
        return Inertia::render('Roles/Index', [
            'title'  => 'Szerepkörök',
            'filter' => $request->validatedFilters(),
        ]);
    }

    /**
     * Adatok az index oldalhoz.
     *
     * Ez a metódus adatokat szolgáltat az index oldalhoz.
     * A metódus egy szerepkör listát ad vissza, amely tartalmazza a szerepkörök adata mezőjét, és a meta adatokat,
     * amely tartalmazza a szerepkörök számát, az oldal számát, a lapok számát, és az utolsó oldal számát.
     * A metódus a szerepkörök listáját egy JsonResponse objektumban adja vissza.
     *
     * @param IndexRequest $request A lekérendő szerepkörök azonosítója.
     * @return JsonResponse A szerepkörök listája egy JsonResponse objektumban.
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
    /*
     * Szerepkör lekérése az azonosítója alapján.
     * Ez a metódus egy szerepkört kér le az azonosítója alapján.
     * Először lekéri a szerepkört az adatbázisból, majd ellenőrzi, hogy a felhasználó jogosult-e megtekinteni azt.
     * Ha a felhasználó jogosult, akkor a szerepkört JSON formátumban adja vissza.
     * Ha a felhasználó nem jogosult, akkor egy 500 Internal Server Error üzenetet ad vissza.
     * @param int $id A lekérendő szerepkör azonosítója.
     * @return JsonResponse A szerepkör JSON formátumban, vagy egy 500 Internal Server Error válasz.
     * @throws Throwable Ha hiba történik a szerepkör lekérése során.
     */
    public function getRole(int $id): JsonResponse
    {
        $role = $this->service->getRole($id);
        $this->authorize('view', $role);
        
        try {
            return response()->json(
                $role,
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
     * @param string $name A lekérendő szerepkör neve.
     * @return JsonResponse A szerepkör JSON válaszként.
     * @throws Throwable Ha a szerepkör nem létezik.
     */
    public function getRoleByName(string $name): JsonResponse
    {
        /** @var Role $role */
        $role = Role::where('name', '=', $name)->firstOrFail();
        $this->authorize('view', $role);
        
        try {
            return response()->json(
                $role,
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
     * @return JsonResponse Egy JSON válasz a létrehozott szerepkörrel.
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
            $role = $this->service->getRole($id);
            $this->authorize('update', $role);
            
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
     * Egyetlen rekord törlése.
     *
     * Engedélyezés: 'delete' policy.
     *
     * @param  int  $id  A törlendo rekord azonosítója.
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
