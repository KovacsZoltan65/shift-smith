<?php

declare(strict_types=1);

namespace App\Repositories\Admin;

use App\Interfaces\Admin\RoleRepositoryInterface;
use App\Models\Admin\Role;
use App\Services\Cache\CacheVersionService;
use App\Services\CacheService;
use App\Traits\Functions;
use Illuminate\Support\Facades\DB;
use Illuminate\Container\Container as AppContainer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Prettus\Repository\Criteria\RequestCriteria;
use Prettus\Repository\Eloquent\BaseRepository;
use Spatie\Permission\PermissionRegistrar;

/**
 * Szerepkör repository osztály
 * 
 * Adatbázis műveletek kezelése szerepkörökhoz (Spatie Permission).
 * Cache támogatással, verziókezeléssel és lapozással.
 * Jogosultságok szinkronizálásával és permission cache kezeléssel.
 */
class RoleRepository extends BaseRepository implements RoleRepositoryInterface
{
    use Functions;

    protected CacheService $cacheService;
    protected string $tag;
    
    private readonly CacheVersionService $cacheVersionService;
    
    /** Cache namespace a szerepkörök listázásához */
    private const NS_ROLES_FETCH = 'roles.fetch';
    /** Cache namespace a szerepkör selector listához */
    private const NS_SELECTORS_ROLES = 'selectors.roles';

    public function __construct(
        AppContainer $app,
        CacheService $cacheService,
        CacheVersionService $cacheVersionService
    )
    {
        parent::__construct($app);

        $this->cacheService = $cacheService;
        $this->tag = Role::getTag();
        $this->cacheVersionService = $cacheVersionService;
    }

    /**
     * Szerepkörök listázása lapozással, szűréssel és rendezéssel
     * 
     * Cache-elhető lekérdezés verziókezeléssel.
     * Tartalmazza a felhasználók számát (users_count) is.
     * Támogatja a keresést (név, guard_name), rendezést és lapozást.
     * 
     * @param Request $request HTTP kérés (search, field, order, per_page, page paraméterekkel)
     * @return LengthAwarePaginator<int, Role> Lapozott szerepkör lista
     */
    public function fetch(Request $request): LengthAwarePaginator
    {
        $needCache = (bool) config('cache.enable_roles', false);

        $page = (int) $request->integer('page', 1);

        $perPage = (int) $request->integer('per_page', 10);
        $perPage = ($perPage > 0) ? min($perPage, 100) : 10;

        $rawTerm = \trim((string) $request->input('search', ''));
        $term = $rawTerm === '' ? null : \mb_strtolower($rawTerm, 'UTF-8');

        $sortable = Role::getSortable();
        $field = \in_array($request->input('field', ''), $sortable, true)
            ? (string) $request->input('field')
            : null;

        $direction = strtolower((string) $request->input('order', '')) === 'desc' ? 'desc' : 'asc';

        $appendQuery = $request->only(['search', 'field', 'order', 'per_page']);

        $queryCallback = function () use ($term, $field, $direction, $perPage, $page, $appendQuery): LengthAwarePaginator {
            $q = Role::query()
                ->withCount('users')
                ->when($term, function ($qq) use ($term) {
                    $qq->where(function ($q) use ($term) {
                        $q->where('name', 'like', "%{$term}%")
                            ->orWhere('guard_name', 'like', "%{$term}%");
                    });
                })
                ->when($field, function ($qq) use ($field, $direction) {
                    if ($field === 'users_count') {
                        return $qq->orderBy('users_count', $direction);
                    }

                    return $qq->orderBy($field, $direction);
                })
                ->when(!$field, fn ($qq) => $qq->orderByDesc('id'));

            $paginator = $q->paginate($perPage, ['*'], 'page', $page);
            $paginator->appends($appendQuery);

            return $paginator;
        };

        if (!$needCache) {
            /** @var LengthAwarePaginator<int, Role> $roles */
            $roles = $queryCallback();
            return $roles;
        }

        $paramsForKey = [
            'page'     => $page,
            'per_page' => $perPage,
            'search'   => $term,
            'field'    => $field,
            'order'    => $direction,
        ];
        ksort($paramsForKey);

        $version = $this->cacheVersionService->get(self::NS_ROLES_FETCH);
        $hash = hash('sha256', json_encode($paramsForKey, JSON_THROW_ON_ERROR));
        $key = "v{$version}:{$hash}";

        /** @var LengthAwarePaginator<int, Role> $roles */
        $roles = $this->cacheService->remember(
            tag: $this->tag,
            key: $key,
            callback: $queryCallback,
            ttl: (int) config('cache.ttl_fetch', 60)
        );

        return $roles;
    }

    /**
     * Rekord lekérése azonosító alapján
     * @param int $id
     * @return \App\Models\Admin\Role
     */
    public function getRole(int $id): Role
    {
        /** @var Role $role */
        $role = Role::findOrFail($id);

        return $role;
    }

    /**
     * Rekord lekérése név alapján
     * @param string $name
     * @return \App\Models\Admin\Role
     */
    public function getRoleByName(string $name): Role
    {
        /** @var Role $role */
        $role = Role::where('name', '=', $name)->firstOrFail();

        return $role;
    }

    /**
     * Új szerepkör létrehozása
     * 
     * Tranzakcióban futtatva, jogosultságok szinkronizálással.
     * Spatie Permission cache flush és saját cache invalidálás.
     * 
     * @param array{
     *   name: string,
     *   guard_name: string,
     *   permission_ids?: array<int>|null
     * } $data Szerepkör adatok
     * @return Role Létrehozott szerepkör
     */
    public function store(array $data): Role
    {
        return DB::transaction(function () use ($data): Role {
            $permissionIds = $data['permission_ids'] ?? null;
            unset($data['permission_ids']);

            /** @var Role $role */
            $role = Role::query()->create($data);

            if (is_array($permissionIds)) {
                $permissionIds = array_values(array_unique(array_map('intval', $permissionIds)));
                $role->syncPermissions($permissionIds);
                app(PermissionRegistrar::class)->forgetCachedPermissions();
            }

            $this->createDefaultSettings($role);

            // Cache ürítése
            $this->invalidateAfterRoleWrite();

            return $role;
        });
    }

    /**
     * Szerepkör adatainak frissítése
     * 
     * Tranzakcióban futtatva, pesszimista zárolással.
     * Jogosultságok szinkronizálása és Spatie Permission cache flush.
     * 
     * @param array{
     *    name: string,
     *    guard_name: string,
     *    permission_ids?: array<int>|null
     * } $data Frissítendő adatok
     * @param int $id Szerepkör azonosító
     * @return Role Frissített szerepkör
     */
    public function update(array $data, $id): Role
    {
        return DB::transaction(function () use ($data, $id) {
            $permissionIds = $data['permission_ids'] ?? null;
            unset($data['permission_ids']);

            /** @var Role $role */
            $role = Role::query()->lockForUpdate()->findOrFail($id);

            $role->fill($data);
            $role->save();
            $role->refresh();

            if (is_array($permissionIds)) {
                $permissionIds = array_values(array_unique(array_map('intval', $permissionIds)));
                $role->syncPermissions($permissionIds);
                app(PermissionRegistrar::class)->forgetCachedPermissions();
                $role->loadMissing('permissions');
            }

            $this->updateDefaultSettings($role);

            // Cache ürítése
            $this->invalidateAfterRoleWrite();

            return $role;
        });
    }

    /**
     * Több szerepkör törlése egyszerre
     * 
     * Tranzakcióban futtatva, cache invalidálással.
     * 
     * @param list<int> $ids Szerepkör azonosítók tömbje
     * @return int A törölt rekordok száma
     */
    public function bulkDelete(array $ids): int
    {
        return DB::transaction(function() use($ids): int {
            $deleted = Role::query()->whereIn('id', $ids)->delete();
            
            $this->invalidateAfterRoleWrite();
            
            return $deleted;
        });
    }
    
    /**
     * Egy szerepkör törlése
     * 
     * Tranzakcióban futtatva, pesszimista zárolással.
     * Törli a kapcsolódó beállításokat és invalidálja a cache-t.
     * 
     * @param int $id Szerepkör azonosító
     * @return bool Sikeres törlés esetén true
     */
    public function destroy(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            /** @var Role $role */
            $role = Role::query()->lockForUpdate()->findOrFail($id);

            $deleted = (bool) $role->delete();

            // Beállítások törlése
            $this->deleteDefaultSettings($role);

            // Cache ürítése
            $this->invalidateAfterRoleWrite();

            return $deleted;
        });
    }

    /**
     * Szerepkörök lekérése select listához
     * 
     * Egyszerűsített szerepkör lista (id, name) dropdown/select mezőkhöz.
     * Cache-elhető.
     * 
     * @param array<string, mixed> $params Szűrési paraméterek (jelenleg nem használt)
     * @return array<int, array{id:int, name:string}> Szerepkörök tömbje
     */
    public function getToSelect(array $params = []): array
    {
        $needCache = (bool) config('cache.enable_roleToSelect', false);

        // normalize (jövőbiztos)
        $params['only_active'] = array_key_exists('only_active', $params) ? (bool) $params['only_active'] : true;
        ksort($params);

        //$onlyActive = (bool) $params['only_active'];

        $queryCallback = function (): array {
            $q = Role::query();

            /** @var array<int, array{id: int, name: string}> $out */
            $out = $q->select(['id', 'name'])
                ->orderBy('name')
                ->get()
                ->map(fn (Role $r): array => [
                    'id' => (int) $r->id,
                    'name' => (string) $r->name,
                ])
                ->values()
                ->all();

            return $out;
        };

        if (!$needCache) {
            return $queryCallback();
        }

        $version = $this->cacheVersionService->get(self::NS_SELECTORS_ROLES);
        $hash = hash('sha256', json_encode($params, JSON_THROW_ON_ERROR));
        $key = "v{$version}:{$hash}";

        return $this->cacheService->remember(
            tag: self::NS_SELECTORS_ROLES,
            key: $key,
            callback: $queryCallback,
            ttl: (int) config('cache.ttl_fetch', 1800)
        );
    }
    
    /**
     * Cache invalidálás szerepkör írási műveletek után
     * 
     * Növeli a verzió számokat a szerepkör listázás és selector cache-ekhez.
     * DB commit után fut, így biztosítva a konzisztenciát.
     * 
     * @return void
     */
    private function invalidateAfterRoleWrite(): void
    {
        DB::afterCommit(function (): void {
            // Roles listázás (Index) cache
            $this->cacheVersionService->bump(self::NS_ROLES_FETCH);

            // RoleSelector cache (ha van)
            $this->cacheVersionService->bump(self::NS_SELECTORS_ROLES);
        });
    }

    /**
     * Alapértelmezett beállítások létrehozása új szerepkörhöz
     * 
     * @param Role $role Szerepkör model
     * @return void
     */
    private function createDefaultSettings(Role $role): void{}

    /**
     * Alapértelmezett beállítások frissítése
     * 
     * @param Role $role Szerepkör model
     * @return void
     */
    private function updateDefaultSettings(Role $role): void{}

    /**
     * Alapértelmezett beállítások törlése
     * 
     * @param Role $role Szerepkör model
     * @return void
     */
    private function deleteDefaultSettings(Role $role): void{}

    /**
     * Repository model osztály megadása
     * 
     * @return string Model osztály neve
     */
    public function model(): string
    {
        return Role::class;
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