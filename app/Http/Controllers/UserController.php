<?php

namespace App\Http\Controllers;

use App\Http\Requests\User\BulkDeleteRequest;
use App\Http\Requests\User\IndexRequest;
use App\Http\Requests\User\StoreRequest;
use App\Http\Requests\User\UpdateRequest;
use App\Models\User;
use App\Policies\UserPolicy;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Felhasználó controller osztály
 * 
 * HTTP kérések kezelése felhasználók CRUD műveleteihez.
 * Inertia.js frontend integráció és JSON API végpontok.
 * Policy-alapú autorizációval és saját fiók védelem.
 */
class UserController extends Controller
{
    /**
     * Constructor
     * 
     * @param UserService $service Felhasználó service
     */
    public function __construct(
        private readonly UserService $service
    ) {
        // Ha használsz Policy-t:
        // $this->authorizeResource(User::class, 'user');
    }

    /**
     * Felhasználók lista oldal megjelenítése
     * 
     * @param IndexRequest $request Validált kérés
     * @return InertiaResponse Inertia válasz a Users/Index komponenssel
     */
    public function index(IndexRequest $request): InertiaResponse
    {
        $this->authorize(UserPolicy::PERM_VIEW_ANY, User::class);
        
        return Inertia::render('Users/Index', [
            'title'  => 'Felhasználók',
            'filter' => $request->validatedFilters(),
        ]);
    }

    /**
     * Felhasználók listázása JSON formátumban
     * 
     * @param IndexRequest $request Validált kérés
     * @return JsonResponse Lapozott felhasználó lista JSON-ben
     */
    public function fetch(IndexRequest $request): JsonResponse
    {
        $this->authorize(UserPolicy::PERM_VIEW_ANY, User::class);

        $users = $this->service->fetch($request);

        return response()->json([
            'data' => $users->items(),
            'meta' => [
                'current_page' => $users->currentPage(),
                'per_page'     => $users->perPage(),
                'total'        => $users->total(),
                'last_page'    => $users->lastPage(),
            ],
            'filter' => $request->validatedFilters(),
        ], Response::HTTP_OK);
    }

    /**
     * Lekéri egy adott rekord adatait azonosító alapján.
     *
     * Engedélyezés: 'view' policy.
     *
     * Sikeres lekérés esetén a rekord adatait JSON formátumban adja vissza.
     * Hiba esetén 500 Internal Server Error választ küld a hibaüzenettel.
     *
     * @param  int  $id  A lekérdezni kívánt cég azonosítója.
     * @return \Illuminate\Http\JsonResponse  A cég adatait tartalmazó JSON válasz.
     *
     * @throws \Throwable  Ha a szolgáltatásrétegben kivétel történik.
     */
    public function getUser(int $id): JsonResponse
    {
        try {
            $user = $this->service->getUser($id);
            $this->authorize(UserPolicy::PERM_VIEW, $user);

            return response()->json(
                $user,
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
     * Felhasználó lekérése név alapján
     * 
     * @param Request $request HTTP kérés
     * @return JsonResponse Felhasználó adatok JSON-ben
     */
    public function byName(Request $request): JsonResponse
    {
        try {
            $user = $this->service->getUserByName($request->input('name'));
            $this->authorize(UserPolicy::PERM_VIEW, $user);

            return response()->json(
                $user,
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
     * Új felhasználó létrehozása
     * 
     * @param StoreRequest $request Validált kérés
     * @return JsonResponse Létrehozott felhasználó JSON-ben
     * @throws \Throwable
     */
    public function store(StoreRequest $request): JsonResponse
    {
        $this->authorize(UserPolicy::PERM_CREATE, User::class);
        
        /** @var array{
         *   name: string,
         *   email: string,
         *   password: string,
         *   roles?: array<int, string>
         * } $data
         */
        $data = $request->validated();
        
        try {
            $user = $this->service->store($data);
            
            return response()->json($user, Response::HTTP_OK);
        } catch(Throwable $th) {
            return response()->json(
                ['message' => 'Váratlan hiba történt'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
    
    /**
     * Jelszó visszaállító email küldése
     * 
     * Saját fiókra nem küldhető.
     * 
     * @param Request $request HTTP kérés
     * @param User $user Felhasználó
     * @return JsonResponse Küldés eredménye JSON-ben
     */
    public function sendPasswordReset(Request $request, User $user): JsonResponse
    {
        $this->authorize(UserPolicy::PERM_UPDATE, $user); // vagy külön ability

        abort_if($request->user()->id === $user->id, 403, 'Saját magadnak innen ne.');

        $status = Password::sendResetLink(['email' => $user->email]);

        if ($status !== Password::RESET_LINK_SENT) {
            return response()->json(['message' => __($status)], 422);
        }

        return response()->json(['message' => 'Email elküldve.']);
    }
    
    /**
     * Felhasználó adatainak frissítése
     * 
     * @param UpdateRequest $request Validált kérés
     * @param int $id Felhasználó azonosító
     * @return JsonResponse Frissített felhasználó JSON-ben
     * @throws \Throwable
     */
    public function update(UpdateRequest $request, int $id): JsonResponse
    {
        $this->authorize(UserPolicy::PERM_UPDATE, User::class);
        
        try {
            $user = $this->service->update($request, $id);

            return response()->json([
                'message' => 'A felhasználó sikeresen frissítve.',
                'data' => $user,
            ], Response::HTTP_OK);
        } catch(Throwable $th) {
            return response()->json(
                ['message' => 'Váratlan hiba történt'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
    
    /**
     * Egy felhasználó törlése
     * 
     * @param int $id Felhasználó azonosító
     * @return JsonResponse Törlés eredménye JSON-ben
     * @throws \Throwable
     */
    public function destroy(int $id): JsonResponse
    {
        $this->authorize(UserPolicy::PERM_DELETE, User::class);
        
        try {
            $deleted = $this->service->destroy($id);

            return response()->json($deleted, Response::HTTP_OK);
        } catch (Throwable $th) {
            if ($th instanceof HttpExceptionInterface) {
                return response()->json(
                    ['message' => $th->getMessage() !== '' ? $th->getMessage() : 'Váratlan hiba történt'],
                    $th->getStatusCode()
                );
            }

            return response()->json(
                ['message' => 'Váratlan hiba történt'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
    
    /**
     * Több felhasználó törlése egyszerre
     * 
     * Saját fiók nem törölhető.
     * 
     * @param BulkDeleteRequest $request Validált kérés
     * @return JsonResponse Törlés eredménye JSON-ben
     * @throws \Throwable
     */
    public function bulkDelete(BulkDeleteRequest $request): JsonResponse
    {
        $this->authorize(UserPolicy::PERM_DELETE, User::class);
        
        /** @var int $authId */
        $authId = $request->user()->id;

        $data = $request->validated();
        
        if (\in_array($authId, $data['ids'], true)) {
            abort(403, 'Saját fiókot nem törölhetsz.');
        }
        
        try {
            $deleted = $this->service->bulkDelete($data['ids']);
            
            return response()->json([
                'message' => 'Sikeres törlés.',
                'deleted' => $deleted,
            ], Response::HTTP_OK);
        } catch(Throwable $th) {
            if ($th instanceof HttpExceptionInterface) {
                return response()->json([
                    'message' => $th->getMessage() !== '' ? $th->getMessage() : 'Törlés sikertelen.',
                ], $th->getStatusCode());
            }

            return response()->json([
                'message' => 'Törlés sikertelen.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        
    }

    /**
     * Egyszerűsített user selector a role-user hozzárendeléshez.
     *
     * @param Request $request
     * @return array<int, array{id:int, name:string, email:string}>
     */
    public function getToSelect(Request $request): array
    {
        $this->authorize(UserPolicy::PERM_VIEW_ANY, User::class);

        return $this->service->getToSelect([
            'search' => trim((string) $request->input('search', '')) ?: null,
        ]);
    }
    
}
