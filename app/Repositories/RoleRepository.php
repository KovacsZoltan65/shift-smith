<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Interfaces\RoleRepositoryInterface;
use App\Models\Role;
use App\Services\Cache\CacheVersionService;
use App\Services\CacheService;
use App\Traits\Functions;
use DB;
use Illuminate\Container\Container as AppContainer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Prettus\Repository\Criteria\RequestCriteria;
use Prettus\Repository\Eloquent\BaseRepository;

class RoleRepository extends BaseRepository implements RoleRepositoryInterface
{
    use Functions;

    protected CacheService $cacheService;
    protected string $tag;
    
    private readonly CacheVersionService $cacheVersionService;
    
    private const NS_ROLES_FETCH = 'roles.fetch';
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
     * 
     * @param Request $request
     * @return LengthAwarePaginator<int, Role>
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
            /** @var LengthAwarePaginator<int, Role> $roles */
            $roles = $queryCallback();
            return $roles;
        }

        $paramsForKey = [
            'page' => $page,
            'per_page' => $perPage,
            'search' => $term,
            'field' => $field,
            'order' => $direction,
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
     * @return \App\Models\Role
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
     * @return \App\Models\Role
     */
    public function getRoleByName(string $name): Role
    {
        /** @var Role $role */
        $role = Role::where('name', '=', $name)->firstOrFail();

        return $role;
    }

    /**
     * Summary of store
     * @param array{
     *   name: string,
     *   guard_name: string,
     * } $data
     * @return Role
     */
    public function store(array $data): Role
    {
        return DB::transaction(function () use ($data): Role {
            /** @var Role $role */
            $role = Role::query()->create($data);

            $this->createDefaultSettings($role);

            // Cache ürítése
            $this->invalidateAfterRoleWrite();

            return $role;
        });
    }

    /**
     * Summary of update
     * @param array{
     *    name: string,
     *    guard_name: string,
     * } $data
     * @param int $id
     * @return Role
     */
    public function update(array $data, $id): Role
    {
        return DB::transaction(function () use ($data, $id) {
            /** @var Role $role */
            $role = Role::query()->lockForUpdate()->findOrFail($id);

            $role->fill($data);
            $role->save();
            $role->refresh();

            $this->updateDefaultSettings($role);

            // Cache ürítése
            $this->invalidateAfterRoleWrite();

            return $role;
        });
    }

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
     * Summary of getToSelect
     * @return array<int, array{id: int, name: string}>
     */
    public function getToSelect(array $params = []): array
    {
        $needCache = (bool) config('cache.enable_roleToSelect', false);

        // normalize (jövőbiztos)
        $params['only_active'] = array_key_exists('only_active', $params) ? (bool) $params['only_active'] : true;
        ksort($params);

        $onlyActive = (bool) $params['only_active'];

        $queryCallback = function () use ($onlyActive): array {
            $q = Role::query();

            if ($onlyActive && method_exists(Role::class, 'active')) {
                $q = Role::active();
            } else {
                // ha nincs scopeActive, akkor maradjon sima query (vagy active mező alapján)
                // $q = $q->where('active', true);
            }

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
            ttl: (int) config('cache.ttl_roleToSelect', 1800)
        );
    }
    
    private function invalidateAfterRoleWrite(): void
    {
        DB::afterCommit(function (): void {
            // Roles listázás (Index) cache
            $this->cacheVersionService->bump(self::NS_ROLES_FETCH);

            // RoleSelector cache (ha van)
            $this->cacheVersionService->bump(self::NS_SELECTORS_ROLES);
        });
    }

    private function createDefaultSettings(Role $role): void{}

    private function updateDefaultSettings(Role $role): void{}

    private function deleteDefaultSettings(Role $role): void{}

    public function model(): string
    {
        return Role::class;
    }

    public function boot(): void
    {
        // Ha később Criteria-t akarsz (pl. query stringből automatikusan),
        // ez maradhat, de most a saját fetch úgyis felülírja a logikát.
        $this->pushCriteria(app(RequestCriteria::class));
    }
}