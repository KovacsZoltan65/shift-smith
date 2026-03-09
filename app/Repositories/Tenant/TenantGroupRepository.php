<?php

declare(strict_types=1);

namespace App\Repositories\Tenant;

use App\Models\Company;
use App\Models\Employee;
use App\Models\TenantGroup;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Models\WorkShift;
use App\Services\Cache\CacheVersionService;
use App\Services\CacheService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Repository a landlord oldali TenantGroup CRUD műveletekhez és cache-biztos listázáshoz.
 *
 * A TenantGroup rekordok nem company scope alatt élnek, mert magát a tenant határt definiálják.
 * Ettől függetlenül minden destruktív művelet előtt lefutnak a company és domain hatásvizsgálatok.
 */
final class TenantGroupRepository implements TenantGroupRepositoryInterface
{
    private const TAG = 'landlord:tenant-groups';
    private const NS_FETCH = 'landlord:tenant-groups:list';
    private const NS_SHOW = 'landlord:tenant-groups:show';

    public function __construct(
        private readonly CacheService $cacheService,
        private readonly CacheVersionService $cacheVersions,
    ) {}

    /**
     * @param array{
     *   search?: ?string,
     *   active?: mixed,
     *   status?: ?string,
     *   sort_field?: ?string,
     *   sort_direction?: ?string,
     *   page?: ?int,
     *   per_page?: ?int
     * } $filters
     * @return LengthAwarePaginator<int, TenantGroup>
     */
    public function fetch(array $filters): LengthAwarePaginator
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = min(max(1, (int) ($filters['per_page'] ?? 10)), 100);
        $search = \is_string($filters['search'] ?? null) ? trim((string) $filters['search']) : null;
        $active = \array_key_exists('active', $filters) ? $filters['active'] : null;
        $status = \is_string($filters['status'] ?? null) ? trim((string) $filters['status']) : null;
        $sortField = \in_array((string) ($filters['sort_field'] ?? ''), TenantGroup::getSortable(), true)
            ? (string) $filters['sort_field']
            : 'created_at';
        $sortDirection = strtolower((string) ($filters['sort_direction'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

        $queryCallback = function () use ($page, $perPage, $search, $active, $status, $sortField, $sortDirection): LengthAwarePaginator {
            return TenantGroup::query()
                ->when($search !== null && $search !== '', function ($query) use ($search): void {
                    $term = mb_strtolower($search, 'UTF-8');

                    $query->where(function ($inner) use ($term): void {
                        $inner->whereRaw('LOWER(name) like ?', ["%{$term}%"])
                            ->orWhereRaw('LOWER(code) like ?', ["%{$term}%"])
                            ->orWhereRaw("LOWER(COALESCE(status, '')) like ?", ["%{$term}%"]);
                    });
                })
                ->when(is_bool($active), fn ($query) => $query->where('active', $active))
                ->when($status !== null && $status !== '', fn ($query) => $query->where('status', $status))
                ->orderBy($sortField, $sortDirection)
                ->orderBy('id', 'desc')
                ->paginate($perPage, ['*'], 'page', $page);
        };

        $keyParams = [
            'page' => $page,
            'per_page' => $perPage,
            'search' => $search,
            'active' => $active,
            'status' => $status,
            'sort_field' => $sortField,
            'sort_direction' => $sortDirection,
        ];
        ksort($keyParams);

        // A landlord oldali listacache verziózott namespace-et használ, így a create/update/delete
        // minden szűrési kombinációt egyszerre tud érvényteleníteni külön kulcsnyilvántartás nélkül.
        /** @var LengthAwarePaginator<int, TenantGroup> $paginator */
        $paginator = $this->cacheService->remember(
            tag: self::TAG,
            key: 'v'.$this->cacheVersions->get(self::NS_FETCH).':'.hash('sha256', json_encode($keyParams, JSON_THROW_ON_ERROR)),
            callback: $queryCallback,
            ttl: (int) config('cache.ttl_fetch', 60),
        );

        return $paginator;
    }

    public function findById(int $id): TenantGroup
    {
        $queryCallback = fn (): TenantGroup => TenantGroup::query()->findOrFail($id);

        /** @var TenantGroup $tenantGroup */
        $tenantGroup = $this->cacheService->remember(
            tag: self::TAG,
            key: 'v'.$this->cacheVersions->get(self::NS_SHOW).":{$id}",
            callback: $queryCallback,
            ttl: (int) config('cache.ttl_fetch', 120),
        );

        return $tenantGroup;
    }

    /**
     * @param array{name:string,code:string,status:?string,active:bool,notes:?string,database_name:?string} $data
     */
    public function create(array $data): TenantGroup
    {
        return DB::transaction(function () use ($data): TenantGroup {
            /** @var TenantGroup $tenantGroup */
            $tenantGroup = TenantGroup::query()->create($data);
            $this->invalidateAfterMutation((int) $tenantGroup->id);

            return $tenantGroup->refresh();
        });
    }

    /**
     * @param array{name:string,code:string,status:?string,active:bool,notes:?string,database_name:?string} $data
     */
    public function update(TenantGroup $tenantGroup, array $data): TenantGroup
    {
        return DB::transaction(function () use ($tenantGroup, $data): TenantGroup {
            /** @var TenantGroup $locked */
            $locked = TenantGroup::query()->lockForUpdate()->findOrFail($tenantGroup->id);
            $locked->fill($data);
            $locked->save();
            $this->invalidateAfterMutation((int) $locked->id);

            return $locked->refresh();
        });
    }

    /**
     * @return array{
     *   company_count:int,
     *   active_company_count:int,
     *   user_count:int,
     *   employee_count:int,
     *   work_schedule_count:int,
     *   work_shift_count:int
     * }
     */
    public function deleteImpact(TenantGroup $tenantGroup): array
    {
        $tenantGroupId = (int) $tenantGroup->id;

        $companyIds = Company::query()
            ->where('tenant_group_id', $tenantGroupId)
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        return [
            'company_count' => count($companyIds),
            'active_company_count' => Company::query()
                ->where('tenant_group_id', $tenantGroupId)
                ->where('active', true)
                ->count(),
            'user_count' => $companyIds === []
                ? 0
                : User::query()->whereHas('companies', fn ($query) => $query->whereIn('companies.id', $companyIds))->distinct('users.id')->count('users.id'),
            'employee_count' => $companyIds === []
                ? 0
                : Employee::query()->whereIn('company_id', $companyIds)->count(),
            'work_schedule_count' => $companyIds === []
                ? 0
                : WorkSchedule::query()->whereIn('company_id', $companyIds)->count(),
            'work_shift_count' => $companyIds === []
                ? 0
                : WorkShift::query()->whereIn('company_id', $companyIds)->count(),
        ];
    }

    public function delete(TenantGroup $tenantGroup): void
    {
        DB::transaction(function () use ($tenantGroup): void {
            /** @var TenantGroup $locked */
            $locked = TenantGroup::query()->lockForUpdate()->findOrFail($tenantGroup->id);
            $locked->delete();
            $this->invalidateAfterMutation((int) $locked->id);
        });
    }

    private function invalidateAfterMutation(int $tenantGroupId): void
    {
        DB::afterCommit(function () use ($tenantGroupId): void {
            $this->cacheVersions->bump(self::NS_FETCH);
            $this->cacheVersions->bump(self::NS_SHOW);
            $this->cacheVersions->bump(self::NS_SHOW.":{$tenantGroupId}");
        });
    }
}
