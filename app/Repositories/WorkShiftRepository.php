<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Interfaces\WorkShiftRepositoryInterface;
use App\Models\Company;
use App\Models\TenantGroup;
use App\Models\WorkShift;
use App\Models\WorkShiftBreak;
use App\Services\Cache\CacheVersionService;
use App\Services\CacheService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class WorkShiftRepository implements WorkShiftRepositoryInterface
{
    private const NS_WORK_SHIFTS_FETCH = 'work_shifts.fetch';
    private const NS_SELECTORS_WORK_SHIFTS = 'selectors.work_shifts';
    private const NS_DASHBOARD_STATS = 'dashboard.stats';

    private readonly string $tag;

    public function __construct(
        private readonly CacheService $cacheService,
        private readonly CacheVersionService $cacheVersionService,
    ) {
        $this->tag = WorkShift::getTag();
    }

    public function paginate(Request $request, int $companyId): LengthAwarePaginator
    {
        $needCache = (bool) config('cache.enable_work_shifts', false);

        $page = max(1, (int) $request->integer('page', 1));
        $perPage = min(max(1, (int) $request->integer('per_page', 10)), 100);
        $companyId = $this->resolveTenantScopedCompanyId($companyId);
        $termRaw = trim((string) $request->input('search', ''));
        $term = $termRaw === '' ? null : mb_strtolower($termRaw, 'UTF-8');
        $field = \in_array((string) $request->input('field', ''), WorkShift::getSortable(), true)
            ? (string) $request->input('field')
            : null;
        $direction = strtolower((string) $request->input('order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $onlyActive = $request->has('active') ? $request->boolean('active') : null;

        $appendQuery = $request->only(['search', 'field', 'order', 'per_page', 'active']);

        $queryCallback = function () use (
            $companyId,
            $term,
            $field,
            $direction,
            $perPage,
            $page,
            $appendQuery,
            $onlyActive
        ): LengthAwarePaginator {
            $query = WorkShift::query()
                ->with(['breaks' => static fn ($q) => $q->orderBy('id')])
                ->withCount('assignments as employees_count')
                ->where('company_id', $companyId)
                ->when($onlyActive !== null, fn ($q) => $q->where('active', $onlyActive))
                ->when($term, fn ($q) => $q->whereRaw('LOWER(name) like ?', ["%{$term}%"]))
                ->when($field, fn ($q) => $q->orderBy($field, $direction))
                ->when(!$field, fn ($q) => $q->orderByDesc('id'));

            $paginator = $query->paginate($perPage, ['*'], 'page', $page);
            $paginator->appends($appendQuery);

            return $paginator;
        };

        if (! $needCache) {
            return $queryCallback();
        }

        $paramsForKey = [
            'page' => $page,
            'per_page' => $perPage,
            'company_id' => $companyId,
            'search' => $term,
            'field' => $field,
            'order' => $direction,
            'active' => $onlyActive,
        ];
        ksort($paramsForKey);

        $version = $this->cacheVersionService->get(self::NS_WORK_SHIFTS_FETCH);
        $hash = hash('sha256', json_encode($paramsForKey, JSON_THROW_ON_ERROR));
        $key = "v{$version}:{$hash}";

        /** @var LengthAwarePaginator<int, WorkShift> $workShifts */
        $workShifts = $this->cacheService->remember(
            tag: $this->tag,
            key: $key,
            callback: $queryCallback,
            ttl: (int) config('cache.ttl_fetch', 60),
        );

        return $workShifts;
    }

    public function findOrFailScoped(int $id, int $companyId): WorkShift
    {
        $scopedCompanyId = $this->resolveTenantScopedCompanyId($companyId);

        /** @var WorkShift $workShift */
        $workShift = WorkShift::query()
            ->with(['breaks' => static fn ($q) => $q->orderBy('id')])
            ->where('company_id', $scopedCompanyId)
            ->findOrFail($id);

        return $workShift;
    }

    public function store(array $data): WorkShift
    {
        $companyId = $this->resolveTenantScopedCompanyId((int) ($data['company_id'] ?? 0));
        $data['company_id'] = $companyId;
        $breakRows = $data['breaks'] ?? [];
        unset($data['breaks']);

        return DB::transaction(function () use ($data, $breakRows, $companyId): WorkShift {
            /** @var WorkShift $workShift */
            $workShift = WorkShift::query()->create($data);
            $this->replaceBreaks($workShift, $breakRows, $companyId);
            $this->invalidateAfterWrite();

            return $workShift->load(['breaks' => static fn ($q) => $q->orderBy('id')]);
        });
    }

    public function update(WorkShift $shift, array $data): WorkShift
    {
        $scopedCompanyId = $this->resolveTenantScopedCompanyId((int) $shift->company_id);
        $breakRows = $data['breaks'] ?? [];
        unset($data['company_id']);
        unset($data['breaks']);

        return DB::transaction(function () use ($data, $breakRows, $shift, $scopedCompanyId): WorkShift {
            /** @var WorkShift $workShift */
            $workShift = WorkShift::query()
                ->where('company_id', $scopedCompanyId)
                ->lockForUpdate()
                ->findOrFail($shift->id);

            $workShift->fill($data);
            $workShift->save();
            $this->replaceBreaks($workShift, $breakRows, $scopedCompanyId);
            $workShift->refresh()->load(['breaks' => static fn ($q) => $q->orderBy('id')]);

            $this->invalidateAfterWrite();

            return $workShift;
        });
    }

    public function bulkDelete(array $ids, int $companyId): int
    {
        $scopedCompanyId = $this->resolveTenantScopedCompanyId($companyId);

        return DB::transaction(function () use ($ids, $scopedCompanyId): int {
            $deleted = (int) WorkShift::query()
                ->where('company_id', $scopedCompanyId)
                ->whereIn('id', $ids)
                ->delete();

            if ($deleted > 0) {
                $this->invalidateAfterWrite();
            }

            return $deleted;
        });
    }

    public function destroy(WorkShift $shift): void
    {
        $scopedCompanyId = $this->resolveTenantScopedCompanyId((int) $shift->company_id);

        DB::transaction(function () use ($shift, $scopedCompanyId): void {
            /** @var WorkShift $workShift */
            $workShift = WorkShift::query()
                ->where('company_id', $scopedCompanyId)
                ->lockForUpdate()
                ->findOrFail($shift->id);

            $workShift->delete();

            $this->invalidateAfterWrite();
        });
    }

    public function getToSelect(array $params, int $companyId): array
    {
        $needCache = (bool) config('cache.enable_work_shiftToSelect', false);

        $companyId = $this->resolveTenantScopedCompanyId($companyId);
        $onlyActive = array_key_exists('only_active', $params) ? (bool) $params['only_active'] : true;
        $searchRaw = trim((string) ($params['search'] ?? ''));
        $search = $searchRaw === '' ? null : mb_strtolower($searchRaw, 'UTF-8');
        $limit = min(max((int) ($params['limit'] ?? 50), 1), 100);

        $keyParams = [
            'company_id' => $companyId,
            'only_active' => $onlyActive,
            'search' => $search,
            'limit' => $limit,
        ];
        ksort($keyParams);

        $queryCallback = function () use ($companyId, $onlyActive, $search, $limit): array {
            return WorkShift::query()
                ->where('company_id', $companyId)
                ->when($onlyActive, fn ($q) => $q->where('active', true))
                ->when($search, fn ($q) => $q->whereRaw('LOWER(name) like ?', ["%{$search}%"]))
                ->select(['id', 'name'])
                ->orderBy('name')
                ->limit($limit)
                ->get()
                ->map(static fn (WorkShift $workShift): array => [
                    'id' => (int) $workShift->id,
                    'name' => (string) $workShift->name,
                ])
                ->values()
                ->all();
        };

        if (! $needCache) {
            return $queryCallback();
        }

        $version = $this->cacheVersionService->get(self::NS_SELECTORS_WORK_SHIFTS);
        $hash = hash('sha256', json_encode($keyParams, JSON_THROW_ON_ERROR));
        $key = "v{$version}:{$hash}";

        /** @var array<int, array{id:int, name:string}> $items */
        $items = $this->cacheService->remember(
            tag: self::NS_SELECTORS_WORK_SHIFTS,
            key: $key,
            callback: $queryCallback,
            ttl: (int) config('cache.ttl_fetch', 1800),
        );

        return $items;
    }

    private function resolveTenantScopedCompanyId(int $companyId): int
    {
        abort_if($companyId <= 0, 403, 'No company selected');

        $query = Company::query()->whereKey($companyId);

        $tenantId = TenantGroup::current()?->id;
        if ($tenantId !== null) {
            $query->where('tenant_group_id', $tenantId);
        }

        $company = $query->firstOrFail(['id']);

        return (int) $company->id;
    }

    private function invalidateAfterWrite(): void
    {
        DB::afterCommit(function (): void {
            $this->cacheVersionService->bump(self::NS_WORK_SHIFTS_FETCH);
            $this->cacheVersionService->bump(self::NS_SELECTORS_WORK_SHIFTS);
            $this->cacheVersionService->bump(self::NS_DASHBOARD_STATS);
        });
    }

    /**
     * @param list<array{break_start_time:string,break_end_time:string,break_minutes:int}> $breakRows
     */
    private function replaceBreaks(WorkShift $shift, array $breakRows, int $companyId): void
    {
        WorkShiftBreak::query()
            ->where('company_id', $companyId)
            ->where('work_shift_id', $shift->id)
            ->delete();

        if ($breakRows === []) {
            return;
        }

        $shift->breaks()->createMany(array_map(
            static fn (array $row): array => [
                'company_id' => $companyId,
                'break_start_time' => $row['break_start_time'],
                'break_end_time' => $row['break_end_time'],
                'break_minutes' => $row['break_minutes'],
            ],
            $breakRows
        ));
    }
}
