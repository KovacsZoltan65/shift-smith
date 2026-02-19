<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Interfaces\WorkPatternRepositoryInterface;
use App\Models\WorkPattern;
use App\Services\Cache\CacheVersionService;
use App\Services\CacheService;
use App\Traits\Functions;
use Illuminate\Container\Container as AppContainer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Override;
use Prettus\Repository\Criteria\RequestCriteria;
use Prettus\Repository\Eloquent\BaseRepository;

/**
 * Munkarend repository osztály.
 *
 * Adatbázis műveletek kezelése munkarendekhez cache támogatással.
 */
class WorkPatternRepository extends BaseRepository implements WorkPatternRepositoryInterface
{
    use Functions;

    protected CacheService $cacheService;

    private readonly CacheVersionService $cacheVersionService;

    private const NS_WORK_PATTERNS_FETCH = 'work_patterns.fetch';
    private const NS_SELECTORS_WORK_PATTERNS = 'selectors.work_patterns';

    public function __construct(
        AppContainer $app,
        CacheService $cacheService,
        CacheVersionService $cacheVersionService
    ) {
        parent::__construct($app);
        $this->cacheService = $cacheService;
        $this->cacheVersionService = $cacheVersionService;
    }

    /**
     * Cache tag előállítása tenant scope-hoz.
     *
     * @param int $companyId Cég azonosító
     * @return string Cache tag
     */
    private function tagForCompany(int $companyId): string
    {
        return "work_patterns:company_{$companyId}";
    }

    /**
     * @inheritDoc
     */
    public function fetch(Request $request): LengthAwarePaginator
    {
        $needCache = (bool) config('cache.enable_work_patterns', false);

        $page = (int) $request->integer('page', 1);
        $perPage = (int) $request->integer('per_page', 10);
        $perPage = ($perPage > 0) ? min($perPage, 100) : 10;

        $companyId = (int) $request->integer('company_id', 0);
        $termRaw = trim((string) $request->input('search', ''));
        $term = $termRaw === '' ? null : mb_strtolower($termRaw, 'UTF-8');

        $field = in_array((string) $request->input('field', ''), WorkPattern::SORTABLE, true)
            ? (string) $request->input('field')
            : null;
        $direction = strtolower((string) $request->input('order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $appendQuery = $request->only(['search', 'field', 'order', 'per_page', 'company_id']);

        $queryCallback = function () use ($companyId, $term, $field, $direction, $perPage, $page, $appendQuery): LengthAwarePaginator {
            $q = WorkPattern::query()
                ->when($companyId > 0, fn ($qq) => $qq->where('company_id', $companyId))
                ->when($term, function ($qq) use ($term): void {
                    $qq->where(function ($q) use ($term): void {
                        $q->whereRaw('LOWER(name) like ?', ["%{$term}%"])
                            ->orWhereRaw('LOWER(type) like ?', ["%{$term}%"]);
                    });
                })
                ->when($field, fn ($qq) => $qq->orderBy($field, $direction))
                ->when(!$field, fn ($qq) => $qq->orderByDesc('id'));

            $paginator = $q->paginate($perPage, ['*'], 'page', $page);
            $paginator->appends($appendQuery);

            return $paginator;
        };

        if (!$needCache || $companyId <= 0) {
            /** @var LengthAwarePaginator<int, WorkPattern> $out */
            $out = $queryCallback();
            return $out;
        }

        $version = $this->cacheVersionService->get(self::NS_WORK_PATTERNS_FETCH . ".company_{$companyId}");
        $paramsForKey = [
            'page' => $page,
            'per_page' => $perPage,
            'company_id' => $companyId,
            'search' => $term,
            'field' => $field,
            'order' => $direction,
        ];
        ksort($paramsForKey);
        $hash = hash('sha256', json_encode($paramsForKey, JSON_THROW_ON_ERROR));
        $key = "v{$version}:{$hash}";

        /** @var LengthAwarePaginator<int, WorkPattern> $out */
        $out = $this->cacheService->remember(
            tag: $this->tagForCompany($companyId),
            key: $key,
            callback: $queryCallback,
            ttl: (int) config('cache.ttl_fetch', 60)
        );

        return $out;
    }

    /**
     * @inheritDoc
     */
    public function getWorkPattern(int $id): WorkPattern
    {
        /** @var WorkPattern $workPattern */
        $workPattern = WorkPattern::query()->findOrFail($id);
        return $workPattern;
    }

    /**
     * @inheritDoc
     */
    public function store(array $data): WorkPattern
    {
        return DB::transaction(function () use ($data): WorkPattern {
            /** @var WorkPattern $workPattern */
            $workPattern = WorkPattern::query()->create($data);
            $this->invalidateAfterWrite((int) $workPattern->company_id);
            return $workPattern;
        });
    }

    /**
     * @inheritDoc
     */
    public function update(array $data, mixed $id): WorkPattern
    {
        return DB::transaction(function () use ($data, $id): WorkPattern {
            /** @var WorkPattern $workPattern */
            $workPattern = WorkPattern::query()->lockForUpdate()->findOrFail($id);
            $workPattern->fill($data);
            $workPattern->save();
            $workPattern->refresh();
            $this->invalidateAfterWrite((int) $workPattern->company_id);
            return $workPattern;
        });
    }

    /**
     * @inheritDoc
     */
    public function bulkDelete(array $ids): int
    {
        return DB::transaction(function () use ($ids): int {
            $companyIds = WorkPattern::query()
                ->whereIn('id', $ids)
                ->pluck('company_id')
                ->unique()
                ->map(fn ($id): int => (int) $id)
                ->values()
                ->all();

            $deleted = (int) WorkPattern::query()->whereIn('id', $ids)->delete();

            foreach ($companyIds as $companyId) {
                $this->invalidateAfterWrite($companyId);
            }

            return $deleted;
        });
    }

    /**
     * @inheritDoc
     */
    public function destroy(int $id): bool
    {
        return DB::transaction(function () use ($id): bool {
            /** @var WorkPattern $workPattern */
            $workPattern = WorkPattern::query()->lockForUpdate()->findOrFail($id);
            $companyId = (int) $workPattern->company_id;
            $deleted = (bool) $workPattern->delete();
            $this->invalidateAfterWrite($companyId);
            return $deleted;
        });
    }

    /**
     * @inheritDoc
     */
    public function getToSelect(int $companyId, bool $onlyActive = true): array
    {
        $needCache = (bool) config('cache.enable_work_pattern_to_select', false);
        $queryCallback = function () use ($companyId, $onlyActive): array {
            /** @var array<int, array{id:int, name:string, type:string}> $out */
            $out = WorkPattern::query()
                ->where('company_id', $companyId)
                ->when($onlyActive, fn ($q) => $q->where('active', true))
                ->select(['id', 'name', 'type'])
                ->orderBy('name')
                ->get()
                ->map(fn (WorkPattern $w): array => [
                    'id' => (int) $w->id,
                    'name' => (string) $w->name,
                    'type' => (string) $w->type,
                ])
                ->values()
                ->all();
            return $out;
        };

        if (!$needCache || $companyId <= 0) {
            return $queryCallback();
        }

        $version = $this->cacheVersionService->get(self::NS_SELECTORS_WORK_PATTERNS . ".company_{$companyId}");
        $hash = hash('sha256', json_encode(['company_id' => $companyId, 'only_active' => $onlyActive], JSON_THROW_ON_ERROR));
        $key = "v{$version}:{$hash}";

        /** @var array<int, array{id:int, name:string, type:string}> */
        return $this->cacheService->remember(
            tag: $this->tagForCompany($companyId),
            key: $key,
            callback: $queryCallback,
            ttl: (int) config('cache.ttl_fetch', 1800)
        );
    }

    /**
     * Cache invalidálás írási műveletek után.
     *
     * @param int $companyId Cég azonosító
     * @return void
     */
    private function invalidateAfterWrite(int $companyId): void
    {
        DB::afterCommit(function () use ($companyId): void {
            $this->cacheVersionService->bump(self::NS_WORK_PATTERNS_FETCH . ".company_{$companyId}");
            $this->cacheVersionService->bump(self::NS_SELECTORS_WORK_PATTERNS . ".company_{$companyId}");
        });
    }

    /**
     * Repository model osztály megadása.
     */
    #[Override]
    public function model(): string
    {
        return WorkPattern::class;
    }

    /**
     * Repository inicializálás.
     */
    public function boot(): void
    {
        $this->pushCriteria(app(RequestCriteria::class));
    }
}
