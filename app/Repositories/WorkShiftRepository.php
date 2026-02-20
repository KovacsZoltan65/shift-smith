<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Interfaces\WorkShiftRepositoryInterface;
use App\Models\WorkShift;
use App\Services\Cache\CacheVersionService;
use App\Services\CacheService;
use App\Traits\Functions;
use Illuminate\Container\Container as AppContainer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Override;
use Prettus\Repository\Criteria\RequestCriteria;
use Prettus\Repository\Eloquent\BaseRepository;

/**
 * Műszak repository.
 */
class WorkShiftRepository extends BaseRepository implements WorkShiftRepositoryInterface
{
    use Functions;

    private readonly CacheVersionService $cacheVersionService;

    /** Cache namespace a műszak listához. */
    private const NS_WORK_SHIFT_FETCH = 'work_shifts.fetch';

    /** Cache namespace a selector listához. */
    private const NS_SELECTORS_WORK_SHIFT = 'selectors.work_shifts';

    public function __construct(
        AppContainer $app,
        private readonly CacheService $cacheService,
        CacheVersionService $cacheVersionService
    ) {
        parent::__construct($app);
        $this->cacheVersionService = $cacheVersionService;
    }

    #[Override]
    public function fetch(Request $request): LengthAwarePaginator
    {
        $needCache = (bool) config('cache.enable_work_shifts', false);

        $page = (int) $request->integer('page', 1);
        $perPage = (int) $request->integer('per_page', 10);
        $perPage = ($perPage > 0) ? min($perPage, 100) : 10;

        $rawTerm = trim((string) $request->input('search', ''));
        $term = $rawTerm === '' ? null : mb_strtolower($rawTerm, 'UTF-8');

        $userCompanyId = $this->currentCompanyId();
        $companyIdRaw = $request->input('company_id');
        $companyId = $userCompanyId > 0
            ? $userCompanyId
            : (($companyIdRaw === null || $companyIdRaw === '') ? null : (int) $companyIdRaw);

        $sortable = WorkShift::getSortable();
        $field = in_array($request->input('field', ''), $sortable, true)
            ? (string) $request->input('field')
            : null;

        $direction = strtolower((string) $request->input('order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $appendQuery = $request->only(['search', 'field', 'order', 'per_page', 'company_id']);

        $queryCallback = function () use ($companyId, $term, $field, $direction, $perPage, $page, $appendQuery): LengthAwarePaginator {
            $query = WorkShift::query()
                ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
                ->when($term, function ($q) use ($term): void {
                    $q->whereRaw('LOWER(name) like ?', ["%{$term}%"]);
                })
                ->when($field, fn ($q) => $q->orderBy($field, $direction))
                ->when(!$field, fn ($q) => $q->orderByDesc('id'));

            $paginator = $query->paginate($perPage, ['*'], 'page', $page);
            $paginator->appends($appendQuery);

            return $paginator;
        };

        if (!$needCache) {
            return $queryCallback();
        }

        $paramsForKey = [
            'page' => $page,
            'per_page' => $perPage,
            'search' => $term,
            'field' => $field,
            'order' => $direction,
            'company_id' => $companyId,
        ];
        ksort($paramsForKey);

        $version = $this->cacheVersionService->get($this->fetchNamespace($companyId));
        $hash = hash('sha256', json_encode($paramsForKey, JSON_THROW_ON_ERROR));
        $key = "v{$version}:{$hash}";

        /** @var LengthAwarePaginator<int, WorkShift> $rows */
        $rows = $this->cacheService->remember(
            tag: $this->tagForCompany($companyId),
            key: $key,
            callback: $queryCallback,
            ttl: (int) config('cache.ttl_fetch', 60)
        );

        return $rows;
    }

    #[Override]
    public function getWorkShift(int $id): WorkShift
    {
        $query = WorkShift::query();
        $companyId = $this->currentCompanyId();
        if ($companyId > 0) {
            $query->where('company_id', $companyId);
        }

        /** @var WorkShift $workShift */
        $workShift = $query->findOrFail($id);

        return $workShift;
    }

    #[Override]
    public function getWorkShiftByName(string $name): WorkShift
    {
        $query = WorkShift::query()->where('name', '=', $name);
        $companyId = $this->currentCompanyId();
        if ($companyId > 0) {
            $query->where('company_id', $companyId);
        }

        /** @var WorkShift $workShift */
        $workShift = $query->firstOrFail();

        return $workShift;
    }

    #[Override]
    public function store(array $data): WorkShift
    {
        return DB::transaction(function () use ($data): WorkShift {
            /** @var WorkShift $workShift */
            $workShift = WorkShift::query()->create($data);

            $this->createDefaultSettings($workShift);
            $this->invalidateAfterWorkShiftWrite((int) $workShift->company_id);

            return $workShift;
        });
    }

    #[Override]
    public function update(array $data, $id): WorkShift
    {
        return DB::transaction(function () use ($data, $id): WorkShift {
            $companyId = $this->currentCompanyId();
            $query = WorkShift::query()->lockForUpdate();
            if ($companyId > 0) {
                $query->where('company_id', $companyId);
            }

            /** @var WorkShift $workShift */
            $workShift = $query->findOrFail($id);
            $workShift->fill($data);
            $workShift->save();
            $workShift->refresh();

            $this->updateDefaultSettings($workShift);
            $this->invalidateAfterWorkShiftWrite((int) $workShift->company_id);

            return $workShift;
        });
    }

    #[Override]
    public function bulkDelete(array $ids): int
    {
        return DB::transaction(function () use ($ids): int {
            $companyId = $this->currentCompanyId();
            $query = WorkShift::query()->whereIn('id', $ids);
            if ($companyId > 0) {
                $query->where('company_id', $companyId);
            }

            $deleted = (int) $query->delete();
            $this->invalidateAfterWorkShiftWrite($companyId > 0 ? $companyId : null);

            return $deleted;
        });
    }

    #[Override]
    public function destroy(int $id): bool
    {
        return DB::transaction(function () use ($id): bool {
            $companyId = $this->currentCompanyId();
            $query = WorkShift::query()->lockForUpdate();
            if ($companyId > 0) {
                $query->where('company_id', $companyId);
            }

            /** @var WorkShift $workShift */
            $workShift = $query->findOrFail($id);
            $deleted = (bool) $workShift->delete();

            $this->deleteDefaultSettings($workShift);
            $this->invalidateAfterWorkShiftWrite((int) $workShift->company_id);

            return $deleted;
        });
    }

    #[Override]
    public function getToSelect(array $params): array
    {
        $needCache = (bool) config('cache.enable_work_shiftToSelect', false);
        $params['only_with_employees'] = !empty($params['only_with_employees']);
        ksort($params);

        $companyId = $this->currentCompanyId();

        $queryCallback = function () use ($companyId): array {
            $query = WorkShift::active()
                ->select(['id', 'name'])
                ->orderBy('name');

            if ($companyId > 0) {
                $query->where('company_id', $companyId);
            }

            /** @var array<int, array{id: int, name: string}> $out */
            $out = $query
                ->get()
                ->map(fn (WorkShift $ws): array => ['id' => (int) $ws->id, 'name' => (string) $ws->name])
                ->values()
                ->all();

            return $out;
        };

        if (!$needCache) {
            return $queryCallback();
        }

        $version = $this->cacheVersionService->get($this->selectorNamespace($companyId));
        $hash = hash('sha256', json_encode($params, JSON_THROW_ON_ERROR));
        $key = "v{$version}:{$hash}";

        return $this->cacheService->remember(
            tag: $this->tagForCompany($companyId),
            key: $key,
            callback: $queryCallback,
            ttl: (int) config('cache.ttl_fetch', 1800)
        );
    }

    /**
     * Cache invalidálás műszak írási műveletek után.
     */
    private function invalidateAfterWorkShiftWrite(?int $companyId): void
    {
        DB::afterCommit(function () use ($companyId): void {
            $this->cacheVersionService->bump(self::NS_WORK_SHIFT_FETCH);
            $this->cacheVersionService->bump(self::NS_SELECTORS_WORK_SHIFT);
            $this->cacheVersionService->bump($this->fetchNamespace(null));
            $this->cacheVersionService->bump($this->selectorNamespace(null));
            $this->cacheVersionService->bump($this->fetchNamespace($companyId));
            $this->cacheVersionService->bump($this->selectorNamespace($companyId));
        });
    }

    /**
     * Aktuális tenant company azonosító.
     */
    private function currentCompanyId(): int
    {
        return (int) (Auth::user()?->company_id ?? 0);
    }

    /**
     * Tenant cache tag előállítása.
     */
    private function tagForCompany(?int $companyId): string
    {
        if ($companyId && $companyId > 0) {
            return WorkShift::getTag().":company_{$companyId}";
        }

        return WorkShift::getTag().':company_global';
    }

    private function fetchNamespace(?int $companyId): string
    {
        return self::NS_WORK_SHIFT_FETCH.'.company_'.($companyId > 0 ? $companyId : 'global');
    }

    private function selectorNamespace(?int $companyId): string
    {
        return self::NS_SELECTORS_WORK_SHIFT.'.company_'.($companyId > 0 ? $companyId : 'global');
    }

    /**
     * Alapértelmezett beállítások létrehozása új műszakhoz.
     */
    private function createDefaultSettings(WorkShift $workShift): void {}

    /**
     * Alapértelmezett beállítások frissítése.
     */
    private function updateDefaultSettings(WorkShift $workShift): void {}

    /**
     * Alapértelmezett beállítások törlése.
     */
    private function deleteDefaultSettings(WorkShift $workShift): void {}

    #[Override]
    public function model(): string
    {
        return WorkShift::class;
    }

    public function boot(): void
    {
        $this->pushCriteria(app(RequestCriteria::class));
    }
}
