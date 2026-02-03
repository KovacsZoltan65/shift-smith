<?php

declare(strict_types=1);

namespace App\Repositories;

use Illuminate\Container\Container as AppContainer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Prettus\Repository\Criteria\RequestCriteria;
use Prettus\Repository\Eloquent\BaseRepository;
use App\Models\Role;
use App\Interfaces\RoleRepositoryInterface;
use App\Services\CacheService;
use App\Traits\Functions;
use DB;

class RoleRepository extends BaseRepository implements RoleRepositoryInterface
{
    use Functions;

    protected CacheService $cacheService;
    protected string $tag;

    public function __construct(AppContainer $app, CacheService $cacheService)
    {
        parent::__construct($app);

        $this->cacheService = $cacheService;
        $this->tag = Role::getTag();
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
            ? $request->input('field')
            : null;

        $direction = strtolower($request->input('order', '')) === 'desc' ? 'desc' : 'asc';

        // a paginátor query-stringje (URL szinkronhoz hasznos)
        $appendQuery = $request->only(['search', 'field', 'order', 'per_page']);

        $queryCallback = function () use ($term, $field, $direction, $perPage, $page, $appendQuery): LengthAwarePaginator {
            $q = Role::query()
                ->when($term, function ($qq) use ($term) {
                    $qq->where(function ($q) use ($term) {
                        $q->where('name', 'like', "%{$term}%")
                            ->orWhere('guard_name', 'like', "%{$term}%");
                    });
                })->when($field, fn($qq) => $qq->orderBy($field, $direction));

            $paginator = $q->paginate($perPage, ['*'], 'page', $page);
            $paginator->appends($appendQuery);

            return $paginator;
        };

        if ($needCache) {
            try {
                $json = json_encode($request->all(), JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                $json = md5(serialize($request->all()));
            }

            $cacheKey = $this->generateCacheKey($this->tag, $json);

            /** @var LengthAwarePaginator<int, Role> $roles */
            $roles = $this->cacheService->remember(
                tag: $this->tag,
                key: $cacheKey,
                callback: $queryCallback
            );
        } else {
            /** @var LengthAwarePaginator<int, Role> $roles */
            $roles = $queryCallback();
        }

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
     * @return Role
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
            $this->cacheService->forgetAll($this->tag);

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
            $this->cacheService->forgetAll($this->tag);

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
            $this->cacheService->forgetAll($this->tag);

            return $deleted;
        });
    }

    /**
     * Summary of getToSelect
     * @return array<int, array{id: int, name: string}>
     */
    public function getToSelect(): array
    {
        return Role::active()
            ->select(['id', 'name'])
            ->orderBy('name')
            ->get()
            ->map(fn(Role $c): array => [
                'id' => (int) $c->id,
                'name' => (string) $c->name,
            ])
            ->values()
            ->all();
    }

    private function createDefaultSettings(Role $role): void
    {
    }

    private function updateDefaultSettings(Role $role): void
    {
    }

    private function deleteDefaultSettings(Role $role): void
    {
    }

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