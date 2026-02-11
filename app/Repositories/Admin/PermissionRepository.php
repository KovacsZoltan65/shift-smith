<?php

declare(strict_types=1);

namespace App\Repositories\Admin;

use App\Interfaces\Admin\PermissionRepositoryInterface;
use App\Models\Admin\Permission;
use App\Services\Cache\CacheVersionService;
use App\Services\CacheService;
use App\Traits\Functions;
use Illuminate\Container\Container as AppContainer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Override;
use Prettus\Repository\Criteria\RequestCriteria;
use Prettus\Repository\Eloquent\BaseRepository;
use DB;

class PermissionRepository extends BaseRepository implements PermissionRepositoryInterface
{
    use Functions;

    protected CacheService $cacheService;
    protected string $tag;
    
    private readonly CacheVersionService $cacheVersionService;
    
    private const NS_PERMISSIONS_FETCH = 'permissions.fetch';
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
     * 
     * @param Request $request
     * @return LengthAwarePaginator<int, Role>
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
        
        $version = $this->cacheVersionService->get(self::NS_ROLES_FETCH);
        $hash = hash('sha256', json_encode($paramsForKey, JSON_THROW_ON_ERROR));
        $key = "v{$version}:{$hash}";

        /** @var LengthAwarePaginator<int, Role> $roles */
        $permission = $this->cacheService->remember(
            tag: $this->tag,
            key: $key,
            callback: $queryCallback,
            ttl: (int) config('cache.ttl_fetch', 60)
        );

        return $permission;
    }
    
    /**
     * Rekord lekérése azonosító alapján
     * @param int $id
     * @return \App\Models\Permission
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
     * @return \App\Models\Permission
     */
    #[Override]
    public function getPermissionByName(string $name): Permission
    {
        /** @var Permission $permission */
        $permission = Permission::where('name', '=', $name)->firstOrFail();

        return $permission;
    }
    
    /**
     * Summary of store
     * @param array{
     *   name: string,
     *   guard_name: string,
     * } $data
     * @return Permission
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
            $this->invalidateAfterRoleWrite();

            return $permission;
        });
    }
    
    /**
     * Summary of update
     * @param array{
     *    name: string,
     *    guard_name: string,
     * } $data
     * @param int $id
     * @return Permission
     */
    public function update(array $data, $id): Permission
    {
        return DB::transaction(function () use ($data, $id) {
            /** @var Permission $role */
            $permission = Permission::query()->lockForUpdate()->findOrFail($id);

            $permission->fill($data);
            $permission->save();
            $permission->refresh();

            $this->updateDefaultSettings($permission);

            // Cache ürítése
            $this->invalidateAfterRoleWrite();

            return $permission;
        });
    }
    
    //
    #[Override]
    public function destroyBulk(array $ids): int
    {
        return DB::transaction(function() use($ids): int {
            
            DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
            DB::table('model_has_permissions')->whereIn('permission_id', $ids)->delete();
            
            $deleted = Permission::query()->whereIn('id', $ids)->delete();
            
            $this->invalidateAfterPermissionWrite();
            
            return $deleted;
        });
    }

    #[Override]
    public function destroy(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            
            DB::table('role_has_permissions')->where('permission_id', $id)->delete();
            DB::table('model_has_permissions')->where('permission_id', $id)->delete();
            
            /** @var Permission $permission */
            $permission = Permission::query()->lockForUpdate()->findOrFail($id);

            $deleted = (bool) $permission->delete();

            // Beállítások törlése
            $this->deleteDefaultSettings($permission);

            // Cache ürítése
            $this->invalidateAfterPermissionWrite();

            return $deleted;
        });
    }
    
    /**
     * Summary of getToSelect
     * @return array<int, array{id: int, name: string}>
     */
    #[Override]
    public function getToSelect(array $params = []): array
    {
        $needCache = (bool) config('cache.enable_permissionToSelect', false);
        
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
    
    private function invalidateAfterPermissionWrite(): void
    {
        DB::afterCommit(function (): void {
            // Permissions listázás (Index) cache
            $this->cacheVersionService->bump(self::NS_PERMISSIONS_FETCH);

            // PermissionSelector cache (ha van)
            $this->cacheVersionService->bump(self::NS_SELECTORS_PERMISSIONS);
        });
    }

    private function createDefaultSettings(Permission $permission): void{}

    private function updateDefaultSettings(Permission $permission): void{}

    private function deleteDefaultSettings(Permission $permission): void{}

    #[Override]
    public function model(): string
    {
        return Permission::class;
    }

    public function boot(): void
    {
        // Ha később Criteria-t akarsz (pl. query stringből automatikusan),
        // ez maradhat, de most a saját fetch úgyis felülírja a logikát.
        $this->pushCriteria(app(RequestCriteria::class));
    }

    
}