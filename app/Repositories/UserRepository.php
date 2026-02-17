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

class UserRepository extends BaseRepository implements UserRepositoryInterface
{
    use Functions;
    
    protected CacheService $cacheService;
    protected string $tag;
    
    private readonly CacheVersionService $cacheVersionService;
    
    private const NS_USERS_FETCH = 'users.fetch';
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
     * Rekord lekérése azonosító alapján
     * 
     * @param int $id
     * @return User
     */
    public function getUser(int $id): User
    {
        /** @var User $u */
        $u = User::findOrFail($id);
        
        return $u;
    }
    
    /**
     * Rekord lekérése név alapján
     * 
     * @param string $name
     * @return User
     */
    public function getUserByName(string $name): User
    {
        /** @var User $u */
        $u = User::where('name', '=', $name)->firstOrFail();
        
        return $u;
    }
    
    /**
     * Új felhasználó mentése
     * 
      * @param array{
      *   name: string,
      *   email: string,
      *   password: string,
      *   company_id?: int|null,
      *   is_active?: bool,
      * } $data
     * @return User
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
     * Felhasználó adatainak mentése
     * 
     * @param array{
     *   name: string,
     *   email: string,
     *   password: string,
     *   company_id?: int|null,
     *   is_active?: bool,
     * } $data
     * @param int $id
     * @return User
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
     * @param list<int> $ids
     * @return int
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
     * Summary of createDefaultSettings
     * @param User $user
     * @return void
     */
    private function createDefaultSettings(User $user): void{}
    
    /**
     * Summary of updateDefaultSettings
     * @param User $user
     * @return void
     */
    private function updateDefaultSettings(User $user): void{}
    
    /**
     * Summary of deleteDefaultSettings
     * @param User $user    
     * @return void
     */
    private function deleteDefaultSettings(User $user): void{}
    
    public function model(): string
    {
        return User::class;
    }

    public function boot(): void
    {
        // Ha később Criteria-t akarsz (pl. query stringből automatikusan),
        // ez maradhat, de most a saját fetch úgyis felülírja a logikát.
        $this->pushCriteria(app(RequestCriteria::class));
    }
}
