<?php

namespace App\Http\Controllers;

use App\Http\Requests\User\BulkDeleteRequest;
use App\Http\Requests\User\IndexRequest;
use App\Http\Requests\User\StoreRequest;
use App\Http\Requests\User\UpdateRequest;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class UserController extends Controller
{
    public function __construct(
        private readonly UserService $service
    ) {
        // Ha használsz Policy-t:
        // $this->authorizeResource(User::class, 'user');
    }

    public function index(IndexRequest $request): InertiaResponse
    {
        $this->authorize('viewAny', User::class);
        
        return Inertia::render('Users/Index', [
            'title'  => 'Felhasználók',
            'filter' => $request->validatedFilters(),
        ]);
    }

    public function fetch(IndexRequest $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

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
        $this->authorize('view', User::class);
        
        try {
            $user = $this->service->getUser($id);

            return response()->json(
                $user,
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
     * Lekéri egy adott rekord adatait azonosító alapján.
     *
     * Engedélyezés: 'view' policy.
     *
     * Sikeres lekérés esetén a rekord adatait JSON formátumban adja vissza.
     * Hiba esetén 500 Internal Server Error választ küld a hibaüzenettel.
     *
     * @param  string  $name  A lekérdezni kívánt rekord neve.
     * @return \Illuminate\Http\JsonResponse  A rekord adatait tartalmazó JSON válasz.
     *
     * @throws \Throwable  Ha a szolgáltatásrétegben kivétel történik.
     */
    public function byName(Request $request): JsonResponse
    {
        $this->authorize('view', User::class);
        
        try {
            $user = $this->service->getUserByName($name);

            return response()->json(
                $user,
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
     * Új rekord létrehozása.
     *
     * Engedélyezés: 'create' policy.
     * Siker esetén a létrehozott rekord adatait adja vissza 201-es státuszkóddal.
     *
     * @throws \Throwable
     */
    public function store(StoreRequest $request): JsonResponse
    {
        $this->authorize('create', User::class);
        
        try {
            $user = $this->service->store($request->validated());
            
            return response()->json($user, Response::HTTP_OK);
        } catch(Throwable $th) {
            return response()->json(
                ['error' => $th->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
    
    public function sendPasswordReset(User $user): JsonResponse
    {
        //$this->authorize('update', $user); // vagy külön ability

        abort_if(auth()->id() === $user->id, 403, 'Saját magadnak innen ne.');

        $status = Password::sendResetLink(['email' => $user->email]);

        if ($status !== Password::RESET_LINK_SENT) {
            return response()->json(['message' => __($status)], 422);
        }

        return response()->json(['message' => 'Email elküldve.']);
    }
    
    /**
     * Meglévő rekord adatainak frissítése.
     *
     * Engedélyezés: 'update' policy.
     *
     * @param  int  $id  A módosítandó rekord azonosítója.
     *
     * @throws \Throwable
     */
    public function update(UpdateRequest $request, int $id): JsonResponse
    {
        $this->authorize('update', User::class);
        
        try {
            $user = $this->service->update($request, $id);

            return response()->json($user, Response::HTTP_OK);
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
     * @throws \Throwable
     */
    public function destroy(int $id): JsonResponse
    {
        $this->authorize('delete', User::class);
        
        try {
            $deleted = $this->service->destroy($id);

            return response()->json($deleted, Response::HTTP_OK);
        } catch (Throwable $th) {
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
     *
     * @throws \Throwable
     */
    public function bulkDelete(BulkDeleteRequest $request): JsonResponse
    {
        $this->authorize('delete', User::class);
        
        $authId = auth()->id();

        $data = $request->validated();
        
        if (in_array($authId, $data['ids'], true)) {
            abort(403, 'Saját fiókot nem törölhetsz.');
        }
        
        try {
            $deleted = $this->service->bulkDelete($data['ids']);
            
            return response()->json([
                'message' => 'Sikeres törlés.',
                'deleted' => $deleted,
            ], Response::HTTP_OK);
        } catch(Throwable $th) {
            \Log::info(print_r($th->getFile(), true));
            \Log::info(print_r($th->getLine(), true));
            \Log::info(print_r($th->getMessage(), true));
            return response()->json([
                'message' => 'Törlés sikertelen.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        
    }
    
}
