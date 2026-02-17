<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Interfaces\UserRepositoryInterface;
use App\Models\User;
use App\Services\Cache\CacheVersionService;
use App\Services\CacheService;
use App\Traits\Functions;
use Illuminate\Container\Container as AppContainer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Auth;
use Prettus\Repository\Criteria\RequestCriteria;
use Prettus\Repository\Eloquent\BaseRepository;
use Symfony\Component\HttpFoundation\Exception\JsonException;

/**
 * Felhasználó repository osztály
 * 
 * Adatbázis műveletek kezelése felhasználókhoz.
 * Cache támogatással, verziókezeléssel és lapozással.
 * Spatie Permission integráció a szerepkörök és jogosultságok kezeléséhez.
 */
class UserRepository extends BaseRepository implements UserRepositoryInterface
{
    use Functions;
    
    protected CacheService $cacheService;
    protected string $tag;
    
    private readonly CacheVersionService $cacheVersionService;
    
    /** Cache namespace a felhasználók listázásához */
    private const NS_USERS_FETCH = 'users.fetch';
    /** Cache namespace a felhasználó selector listához */
    private const NS_SELECTORS_USERS = 'selectors.users';
    
    public function __construct(
        AppContainer $app,
        CacheService $cacheService,
        CacheVersionService $cacheVersionService
    ) {
        parent::__construct($app);

        $this->cacheService = $cacheService;
        $this->tag = User::getTag();
        $this->cacheVersionService = $cacheVersionService;
    }

    /**
     * Felhasználók listázása lapozással, szűréssel és rendezéssel
     * 
     * Cache-elhető lekérdezés verziókezeléssel.
     * Támogatja a keresést (név, email), rendezést és lapozást.
     * 
     * @param Request $request HTTP kérés (search, field, order, per_page, page paraméterekkel)
     * @return LengthAwarePaginator<int, User> Lapozott felhasználó lista
     */
    public function fetch(Request $request): LengthAwarePaginator
    {
        $needCache = (bool) config('cache.enable_users', false);

        $page = (int) $request->integer('page', 1);

        $perPage = (int) $request->integer('per_page', 10);
        $perPage = ($perPage > 0) ? min($perPage, 100) : 10;

        $rawTerm = \trim((string) $request->input('search', ''));
        $term = $rawTerm === '' ? null : \mb_strtolower($rawTerm, 'UTF-8');

        $sortable = User::getSortable();
        $field = \in_array($request->input('field', ''), $sortable, true)
            ? (string) $request->input('field')
            : null;

        $direction = strtolower((string) $request->input('order', '')) === 'desc' ? 'desc' : 'asc';

        $appendQuery = $request->only(['search', 'field', 'order', 'per_page']);

        $queryCallback = function () use ($term, $field, $direction, $perPage, $page, $appendQuery): LengthAwarePaginator {
            $q = User::query()
                ->when($term, function ($qq) use ($term) {
                    $qq->where(function ($q) use ($term) {
                        $q->where('name', 'like', "%{$term}%")
                          ->orWhere('email', 'like', "%{$term}%");
                    });
                })
                ->when($field, fn ($qq) => $qq->orderBy($field, $direction))
                ->when(!$field, fn ($qq) => $qq->orderByDesc('id'));

            $paginator = $q->paginate($perPage, ['*'], 'page', $page);
            $paginator->appends($appendQuery);

            return $paginator;
        };

        if (!$needCache) {
            /** @var LengthAwarePaginator<int, User> $users */
            $users = $queryCallback();
            return $users;
        }

        $paramsForKey = [
            'page' => $page,
            'per_page' => $perPage,
            'search' => $term,
            'field' => $field,
            'order' => $direction,
        ];
        ksort($paramsForKey);

        $version = $this->cacheVersionService->get(self::NS_USERS_FETCH);
        $hash = hash('sha256', json_encode($paramsForKey, JSON_THROW_ON_ERROR));
        $key = "v{$version}:{$hash}";

        /** @var LengthAwarePaginator<int, User> $users */
        $users = $this->cacheService->remember(
            tag: $this->tag,
            key: $key,
            callback: $queryCallback,
            ttl: (int) config('cache.ttl_fetch', 60)
        );

        return $users;
    }
        
    /**
     * Felhasználó lekérése azonosító alapján
     * 
     * @param int $id Felhasználó azonosító
     * @return User Felhasználó model
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException Ha a rekord nem található
     */
    public function getUser(int $id): User
    {
        /** @var User $u */
        $u = User::findOrFail($id);
        
        return $u;
    }
    
    /**
     * Felhasználó lekérése név alapján
     * 
     * @param string $name Felhasználó neve
     * @return User Felhasználó model
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException Ha a rekord nem található
     */
    public function getUserByName(string $name): User
    {
        /** @var User $u */
        $u = User::where('name', '=', $name)->firstOrFail();
        
        return $u;
    }
    
    /**
     * Új felhasználó létrehozása
     * 
     * Tranzakcióban futtatva, jelszó reset link küldéssel.
     * Létrehozás után cache invalidálás.
     * 
      * @param array{
      *   name: string,
      *   email: string,
      *   password: string,
      *   company_id?: int|null,
      *   is_active?: bool,
      * } $data Felhasználó adatok
     * @return User Létrehozott felhasználó
     */
    public function store(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            /** @var User $user */
            $user = User::query()->create($data);
//            $user = User::query()->create([
//                'name' => $data['name'],
//                'email' => $data['email'],
//                'password' => Hash::make( $data['password'] ),
//            ]);

            // küldjünk reset linket azonnal
            $status = Password::sendResetLink(['email' => $user->email]);
            
            $this->createDefaultSettings($user);
            // Cache ürítése
            $this->invalidateAfterUserWrite();

            return $user;
        });
    }
    
    /**
     * Felhasználó adatainak frissítése
     * 
     * Tranzakcióban futtatva, pesszimista zárolással.
     * Frissítés után cache invalidálás.
     * 
     * @param array{
     *   name: string,
     *   email: string,
     *   password: string,
     *   company_id?: int|null,
     *   is_active?: bool,
     * } $data Frissítendő adatok
     * @param int $id Felhasználó azonosító
     * @return User Frissített felhasználó
     */
    public function update(array $data, $id): User
    {
        return DB::transaction(function() use ($data, $id): User {
            /** @var User $user */
            $user = User::query()->lockForUpdate()->findOrFail($id);
            
            $user->fill($data);
            $user->save();
            $user->refresh();
            
            $this->updateDefaultSettings($user);
            // Cache ürítése
            $this->invalidateAfterUserWrite();
            
            return $user;
        });
    }
    
    /**
     * Több felhasználó törlése egyszerre
     * 
     * Tranzakcióban futtatva, szerepkörök és jogosultságok törléssel.
     * Saját fiók törlése tiltva (403 hiba).
     * 
     * @param list<int> $ids Felhasználó azonosítók tömbje
     * @return int A törölt rekordok száma
     */
    public function bulkDelete(array $ids): int
    {
        $authUser = Auth::user();
        abort_if($authUser && \in_array($authUser->id, $ids, true), 403, 'Saját fiókot nem törölhetsz.');
    
        return DB::transaction(function() use($ids): int {
            
            // Jogosultságok törlése
            DB::table('model_has_roles')
                ->where('model_type', User::class)
                ->whereIn('model_id', $ids)
                ->delete();
            
            DB::table('model_has_permissions')
                ->where('model_type', User::class)
                ->whereIn('model_id', $ids)
                ->delete();
            
            $deleted = User::query()->whereIn('id', $ids)->delete();
            
            $this->invalidateAfterUserWrite();
            
            return $deleted;
        });
    }
    
    /**
     * Egy felhasználó törlése
     * 
     * Tranzakcióban futtatva, pesszimista zárolással.
     * Törli a szerepköröket, jogosultságokat és beállításokat.
     * Saját fiók törlése tiltva (403 hiba).
     * 
     * @param int $id Felhasználó azonosító
     * @return bool Sikeres törlés esetén true
     */
    public function destroy(int $id): bool
    {
        return DB::transaction(function() use($id) {
            /** @var User $user */
            $user = User::query()->lockForUpdate()->findOrFail($id);
            
            $authUser = Auth::user();
            abort_if(
                $authUser && $authUser->id === $user->id, 
                403, 
                'Saját fiókot nem törölhetsz.'
            );
            
            DB::table('model_has_roles')
                ->where('model_type', User::class)
                ->where('model_id', '=', $id)
                ->delete();
            
            DB::table('model_has_permissions')
                ->where('model_type', User::class)
                ->where('model_id', '=', $id)
                ->delete();
            
            $deleted = (bool) $user->delete();
            
            // Beállítások törlése
            $this->deleteDefaultSettings($user);
            
            // Cache ürítése
            $this->invalidateAfterUserWrite();

            return $deleted;
        });
    }
    
    /**
     * Cache invalidálás felhasználó írási műveletek után
     * 
     * Növeli a verzió számokat a felhasználó listázás és selector cache-ekhez.
     * DB commit után fut, így biztosítva a konzisztenciát.
     * 
     * @return void
     */
    private function invalidateAfterUserWrite(): void
    {
        DB::afterCommit(function():void {
            // Users lista oldal cache
            $this->cacheVersionService->bump(self::NS_USERS_FETCH);

            // UserSelector cache (mert a selector aktív felhasználókat listáz)
            $this->cacheVersionService->bump(self::NS_SELECTORS_USERS);
        });
    }
    
    /**
     * Alapértelmezett beállítások létrehozása új felhasználóhoz
     * 
     * @param User $user Felhasználó model
     * @return void
     */
    private function createDefaultSettings(User $user): void{}
    
    /**
     * Alapértelmezett beállítások frissítése
     * 
     * @param User $user Felhasználó model
     * @return void
     */
    private function updateDefaultSettings(User $user): void{}
    
    /**
     * Alapértelmezett beállítások törlése
     * 
     * @param User $user Felhasználó model
     * @return void
     */
    private function deleteDefaultSettings(User $user): void{}
    
    /**
     * Repository model osztály megadása
     * 
     * @return string Model osztály neve
     */
    public function model(): string
    {
        return User::class;
    }

    /**
     * Repository inicializálás
     * 
     * Criteria-k regisztrálása (pl. query string alapú szűrés).
     * 
     * @return void
     */
    public function boot(): void
    {
        // Ha később Criteria-t akarsz (pl. query stringből automatikusan),
        // ez maradhat, de most a saját fetch úgyis felülírja a logikát.
        $this->pushCriteria(app(RequestCriteria::class));
    }
}
