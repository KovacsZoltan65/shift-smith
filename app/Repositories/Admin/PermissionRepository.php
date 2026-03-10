<?php

declare(strict_types=1);

namespace App\Repositories\Admin;

use App\Interfaces\Admin\PermissionRepositoryInterface;
use App\Models\Admin\Permission;
use App\Models\TenantGroup;
use App\Services\Cache\CacheVersionService;
use App\Services\CacheService;
use App\Traits\Functions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Container\Container as AppContainer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Override;
use Prettus\Repository\Criteria\RequestCriteria;
use Prettus\Repository\Eloquent\BaseRepository;
use Illuminate\Support\Facades\DB;

/**
 * Jogosultságok repository rétege.
 *
 * A repository a landlord-szintű permission lekérdezésekért és írásokért felel,
 * beleértve a cache-verziózás kezelését is. Authorization döntést nem hozhat,
 * legfeljebb a landlord kontextus minimális védelmét ellenőrzi az írási műveletek előtt.
 */
class PermissionRepository extends BaseRepository implements PermissionRepositoryInterface
{
    use Functions;

    protected CacheService $cacheService;
    protected string $tag;
    
    private readonly CacheVersionService $cacheVersionService;
    
    /** Cache namespace a jogosultságok listázásához */
    private const NS_PERMISSIONS_FETCH = 'permissions.fetch';
    /** Cache namespace a jogosultság selector listához */
    private const NS_SELECTORS_PERMISSIONS = 'selectors.permissions';

    public function __construct(
        AppContainer $app,
        CacheService $cacheService,
        CacheVersionService $cacheVersionService
    )
    {
        parent::__construct($app);

        $this->cacheService = $cacheService;
        $this->tag = Permission::getTag();
        $this->cacheVersionService = $cacheVersionService;
    }
    
    /**
     * Landlord scope-ban lapozott jogosultságlistát ad vissza.
     *
     * A lekérdezés nem tenant-scoped entitást kezel, ezért itt a landlord kontextus az elvárt
     * működés. A cache kulcs csak a szűrőparaméterekből és a verzióból áll.
     *
     * @param Request $request
     * @return LengthAwarePaginator<int, Permission>
     */
    #[Override]
    public function fetch(Request $request): LengthAwarePaginator
    {
        $needCache = (bool) config('cache.enable_permissions', false);
        
        $page = (int) $request->integer('page', 1);

        $perPage = (int) $request->integer('per_page', 10);
        $perPage = ($perPage > 0) ? min($perPage, 100) : 10;

        $rawTerm = \trim((string) $request->input('search', ''));
        $term = $rawTerm === '' ? null : \mb_strtolower($rawTerm, 'UTF-8');

        $sortable = Permission::getSortable();
        $field = \in_array($request->input('field', ''), $sortable, true)
            ? (string) $request->input('field')
            : null;

        $direction = strtolower((string) $request->input('order', '')) === 'desc' ? 'desc' : 'asc';

        $appendQuery = $request->only(['search', 'field', 'order', 'per_page']);
        
        $queryCallback = function () use ($term, $field, $direction, $perPage, $page, $appendQuery): LengthAwarePaginator {
            $q = Permission::query()
                ->when($term, function ($qq) use ($term) {
                    $qq->where(function ($q) use ($term) {
                        $q->where('name', 'like', "%{$term}%")
                            ->orWhere('guard_name', 'like', "%{$term}%");
                    });
                })
                ->when($field, fn ($qq) => $qq->orderBy($field, $direction))
                ->when(!$field, fn ($qq) => $qq->orderByDesc('id'));

            $paginator = $q->paginate($perPage, ['*'], 'page', $page);
            $paginator->appends($appendQuery);

            return $paginator;
        };
        
        if (!$needCache) {
            /** @var LengthAwarePaginator<int, Permission> $permissions */
            $permissions = $queryCallback();
            return $permissions;
        }
        
        $paramsForKey = [
            'page'     => $page,
            'per_page' => $perPage,
            'search'   => $term,
            'field'    => $field,
            'order'    => $direction,
        ];
        ksort($paramsForKey);
        
        $version = $this->cacheVersionService->get(self::NS_PERMISSIONS_FETCH);
        $hash = hash('sha256', json_encode($paramsForKey, JSON_THROW_ON_ERROR));
        $key = "v{$version}:{$hash}";

        /** @var LengthAwarePaginator<int, Permission> $permissions */
        $permissions = $this->cacheService->remember(
            tag: $this->tag,
            key: $key,
            callback: $queryCallback,
            ttl: (int) config('cache.ttl_fetch', 60)
        );

        return $permissions;
    }
    
    /**
     * Rekord lekérése azonosító alapján
     * @param int $id
     * @return \App\Models\Admin\Permission
     */
    #[Override]
    public function getPermission(int $id): Permission
    {
        /** @var Permission $permission */
        $permission = Permission::findOrFail($id);

        return $permission;
    }
    
    /**
     * Rekord lekérése név alapján
     * @param string $name
     * @return \App\Models\Admin\Permission
     */
    #[Override]
    public function getPermissionByName(string $name): Permission
    {
        /** @var Permission $permission */
        $permission = Permission::where('name', '=', $name)->firstOrFail();

        return $permission;
    }
    
    /**
     * Új jogosultság létrehozása
     * 
     * Tranzakcióban futtatva, alapértelmezett beállításokkal.
     * Létrehozás után cache invalidálás.
     * 
     * @param array{
     *   name: string,
     *   guard_name: string,
     * } $data Jogosultság adatok
     * @return Permission Létrehozott jogosultság
     */
    #[Override]
    public function store(array $data): Permission
    {
        return DB::transaction(function() use($data) {
            /** @var Permission $permission */
            $permission = Permission::query()->create([
                'name' => (string) $data['name'],
                'guard_name' => (string) $data['guard_name'],
                //'name' => $request->string('name')->toString(),
                //'guard_name' => $request->string('guard_name')->toString(),
            ]);
            
            $this->createDefaultSettings($permission);
            
            // Cache ürítése
            $this->invalidateAfterPermissionWrite();

            return $permission;
        });
    }
    
    /**
     * Jogosultság adatainak frissítése
     * 
     * Tranzakcióban futtatva, pesszimista zárolással.
     * Frissítés után cache invalidálás.
     * 
     * @param array{
     *    name: string,
     *    guard_name: string,
     * } $data Frissítendő adatok
     * @param int $id Jogosultság azonosító
     * @return Permission Frissített jogosultság
     */
    public function update(array $data, $id): Permission
    {
        return DB::transaction(function () use ($data, $id) {
            /** @var Permission $permission */
            $permission = Permission::query()->lockForUpdate()->findOrFail($id);

            $permission->fill($data);
            $permission->save();
            $permission->refresh();

            $this->updateDefaultSettings($permission);

            // Cache ürítése
            $this->invalidateAfterPermissionWrite();

            return $permission;
        });
    }
    
    //
    /**
     * Több jogosultság törlése egyszerre
     * 
     * Tranzakcióban futtatva, kapcsolódó szerepkör és model kapcsolatok törléssel.
     * Cache invalidálás.
     * 
     * @param list<int> $ids Jogosultság azonosítók tömbje
     * @return int A törölt rekordok száma
     */
    #[Override]
    public function destroyBulk(array $ids): int
    {
        return DB::transaction(function() use($ids): int {
            
            $this->assertLandlordPermissionMutationAllowed();

            $permissions = Permission::query()
                ->whereIn('id', $ids)
                ->lockForUpdate()
                ->get();

            foreach ($permissions as $permission) {
                $this->clearAssignments($permission);
            }

            $deleted = Permission::query()->whereIn('id', $ids)->delete();
            
            $this->invalidateAfterPermissionWrite();
            
            return $deleted;
        });
    }

    /**
     * Egy jogosultság törlése
     * 
     * Tranzakcióban futtatva, pesszimista zárolással.
     * Törli a szerepkör és model kapcsolatokat, beállításokat.
     * Cache invalidálás.
     * 
     * @param int $id Jogosultság azonosító
     * @return bool Sikeres törlés esetén true
     */
    #[Override]
    public function destroy(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            
            $this->assertLandlordPermissionMutationAllowed();
            
            /** @var Permission $permission */
            $permission = Permission::query()->lockForUpdate()->findOrFail($id);
            $this->clearAssignments($permission);

            $deleted = (bool) $permission->delete();

            // Beállítások törlése
            $this->deleteDefaultSettings($permission);

            // Cache ürítése
            $this->invalidateAfterPermissionWrite();

            return $deleted;
        });
    }
    
    /**
     * Egyszerűsített select lista a permission selector komponensekhez.
     *
     * @param array<string, mixed> $params
     * @return array<int, array{id:int, name:string}>
     */
    #[Override]
    public function getToSelect(array $params = []): array
    {
        $needCache = (bool) config('cache.enable_permisionToSelect', false);
        
        // normalize (jövőbiztos)
        $params['only_active'] = array_key_exists('only_active', $params) ? (bool) $params['only_active'] : true;
        ksort($params);
        
        $queryCallback = function (): array {
            $q = Permission::query();

            /** @var array<int, array{id: int, name: string}> $out */
            $out = $q->select(['id', 'name'])
                ->orderBy('name')
                ->get()
                ->map(fn (Permission $p): array => [
                    'id' => (int) $p->id,
                    'name' => (string) $p->name,
                ])
                ->values()
                ->all();

            return $out;
        };
        
        if (!$needCache) {
            return $queryCallback();
        }
        
        $version = $this->cacheVersionService->get(self::NS_SELECTORS_PERMISSIONS);
        $hash = hash('sha256', json_encode($params, JSON_THROW_ON_ERROR));
        $key = "v{$version}:{$hash}";
        
        return $this->cacheService->remember(
            tag: self::NS_SELECTORS_PERMISSIONS,
            key: $key,
            callback: $queryCallback,
            ttl: (int) config('cache.ttl_fetch', 1800)
        );
    }
    
    /**
     * Commit után bumpolja a permission listázó és selector cache verziókat.
     */
    private function invalidateAfterPermissionWrite(): void
    {
        DB::afterCommit(function (): void {
            // Permissions listázás (Index) cache
            $this->cacheVersionService->bump(self::NS_PERMISSIONS_FETCH);

            // PermissionSelector cache (ha van)
            $this->cacheVersionService->bump(self::NS_SELECTORS_PERMISSIONS);
        });
    }

    /**
     * Alapértelmezett beállítások létrehozása új jogosultsághoz
     * 
     * @param Permission $permission Jogosultság model
     * @return void
     */
    private function createDefaultSettings(Permission $permission): void{}

    /**
     * Alapértelmezett beállítások frissítése
     * 
     * @param Permission $permission Jogosultság model
     * @return void
     */
    private function updateDefaultSettings(Permission $permission): void{}

    /**
     * Alapértelmezett beállítások törlése
     * 
     * @param Permission $permission Jogosultság model
     * @return void
     */
    private function deleteDefaultSettings(Permission $permission): void{}

    private function clearAssignments(Permission $permission): void
    {
        $permission->roles()->detach();

        if (method_exists($permission, 'users')) {
            $permission->users()->detach();
        }
    }

    private function assertLandlordPermissionMutationAllowed(): void
    {
        $user = Auth::user();

        if ($user === null) {
            return;
        }

        $isSuperadmin = method_exists($user, 'hasRole') && $user->hasRole('superadmin');
        $isLandlordContext = TenantGroup::current() === null;

        // A permission törzsadat globális, ezért tenant kontextusban nem módosítható biztonságosan.
        abort_if(! ($isSuperadmin && $isLandlordContext), 403, __('permissions.errors.landlord_only'));
    }

    /**
     * Repository model osztály megadása
     * 
     * @return string Model osztály neve
     */
    #[Override]
    public function model(): string
    {
        return Permission::class;
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
