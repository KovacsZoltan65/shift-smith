<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Interfaces\WorkScheduleRepositoryInterface;
use App\Models\WorkSchedule;
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
 * Munkabeosztás repository.
 */
class WorkScheduleRepository extends BaseRepository implements WorkScheduleRepositoryInterface
{
    use Functions;

    private readonly CacheVersionService $cacheVersionService;

    /** Cache namespace a munkabeosztás listához. */
    private const NS_WORK_SCHEDULES_FETCH = 'work_schedules.fetch';

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
        $needCache = (bool) config('cache.enable_work_schedules', false);

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

        $statusRaw = trim((string) $request->input('status', ''));
        $status = $statusRaw === '' ? null : $statusRaw;
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $sortable = WorkSchedule::getSortable();
        $field = in_array($request->input('field', ''), $sortable, true)
            ? (string) $request->input('field')
            : null;

        $orderRaw = (string) $request->input('order', 'desc');
        $direction = strtolower($orderRaw) === 'asc' ? 'asc' : 'desc';
        $appendQuery = $request->only(['search', 'field', 'order', 'per_page', 'company_id', 'status', 'date_from', 'date_to']);

        $queryCallback = function () use ($term, $companyId, $status, $dateFrom, $dateTo, $field, $direction, $perPage, $page, $appendQuery): LengthAwarePaginator {
            $query = WorkSchedule::query()
                ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
                ->when($status, fn ($q) => $q->where('status', $status))
                ->when($dateFrom, fn ($q) => $q->whereDate('date_from', '>=', $dateFrom))
                ->when($dateTo, fn ($q) => $q->whereDate('date_to', '<=', $dateTo))
                ->when($term, function ($q) use ($term): void {
                    $q->where(function ($inner) use ($term): void {
                        $inner->whereRaw('LOWER(name) like ?', ["%{$term}%"])
                            ->orWhereRaw('LOWER(status) like ?', ["%{$term}%"]);
                    });
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
            'company_id' => $companyId,
            'status' => $status,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'field' => $field,
            'order' => $direction,
        ];
        ksort($paramsForKey);

        $version = $this->cacheVersionService->get($this->fetchNamespace($companyId));
        $hash = hash('sha256', json_encode($paramsForKey, JSON_THROW_ON_ERROR));
        $key = "v{$version}:{$hash}";

        /** @var LengthAwarePaginator<int, WorkSchedule> $rows */
        $rows = $this->cacheService->remember(
            tag: $this->tagForCompany($companyId),
            key: $key,
            callback: $queryCallback,
            ttl: (int) config('cache.ttl_fetch', 60)
        );

        return $rows;
    }

    #[Override]
    public function getWorkSchedule(int $id): WorkSchedule
    {
        $query = WorkSchedule::query();
        $companyId = $this->currentCompanyId();
        if ($companyId > 0) {
            $query->where('company_id', $companyId);
        }

        /** @var WorkSchedule $workSchedule */
        $workSchedule = $query->findOrFail($id);
        return $workSchedule;
    }

    #[Override]
    public function store(array $data): WorkSchedule
    {
        return DB::transaction(function () use ($data): WorkSchedule {
            /** @var WorkSchedule $workSchedule */
            $workSchedule = WorkSchedule::query()->create($data);
            $this->invalidateAfterWrite((int) $workSchedule->company_id);
            return $workSchedule;
        });
    }

    #[Override]
    public function update(array $data, $id): WorkSchedule
    {
        return DB::transaction(function () use ($data, $id): WorkSchedule {
            $query = WorkSchedule::query()->lockForUpdate();
            $companyId = $this->currentCompanyId();
            if ($companyId > 0) {
                $query->where('company_id', $companyId);
            }

            /** @var WorkSchedule $workSchedule */
            $workSchedule = $query->findOrFail($id);
            $workSchedule->fill($data);
            $workSchedule->save();
            $workSchedule->refresh();

            $this->invalidateAfterWrite((int) $workSchedule->company_id);

            return $workSchedule;
        });
    }

    #[Override]
    public function bulkDelete(array $ids): int
    {
        return DB::transaction(function () use ($ids): int {
            $companyId = $this->currentCompanyId();

            $publishedQuery = WorkSchedule::query()->whereIn('id', $ids)->where('status', 'published');
            if ($companyId > 0) {
                $publishedQuery->where('company_id', $companyId);
            }

            if ($publishedQuery->exists()) {
                throw new \RuntimeException('Publikált beosztás nem törölhető.');
            }

            $deleteQuery = WorkSchedule::query()->whereIn('id', $ids);
            if ($companyId > 0) {
                $deleteQuery->where('company_id', $companyId);
            }

            $deleted = (int) $deleteQuery->delete();
            $this->invalidateAfterWrite($companyId > 0 ? $companyId : null);

            return $deleted;
        });
    }

    #[Override]
    public function destroy(int $id): bool
    {
        return DB::transaction(function () use ($id): bool {
            $query = WorkSchedule::query()->lockForUpdate();
            $companyId = $this->currentCompanyId();
            if ($companyId > 0) {
                $query->where('company_id', $companyId);
            }

            /** @var WorkSchedule $workSchedule */
            $workSchedule = $query->findOrFail($id);
            if ($workSchedule->status === 'published') {
                throw new \RuntimeException('Publikált beosztás nem törölhető.');
            }

            $deleted = (bool) $workSchedule->delete();
            $this->invalidateAfterWrite((int) $workSchedule->company_id);

            return $deleted;
        });
    }

    /**
     * Cache invalidálás munkabeosztás írási műveletek után.
     */
    private function invalidateAfterWrite(?int $companyId): void
    {
        DB::afterCommit(function () use ($companyId): void {
            $this->cacheVersionService->bump(self::NS_WORK_SCHEDULES_FETCH);
            $this->cacheVersionService->bump($this->fetchNamespace(null));
            $this->cacheVersionService->bump($this->fetchNamespace($companyId));
        });
    }

    /**
     * Aktuális tenant company azonosító.
     */
    private function currentCompanyId(): int
    {
        return (int) (Auth::user()?->company_id ?? 0);
    }

    private function tagForCompany(?int $companyId): string
    {
        if ($companyId && $companyId > 0) {
            return WorkSchedule::getTag().":company_{$companyId}";
        }

        return WorkSchedule::getTag().':company_global';
    }

    private function fetchNamespace(?int $companyId): string
    {
        return self::NS_WORK_SCHEDULES_FETCH.'.company_'.($companyId > 0 ? $companyId : 'global');
    }

    #[Override]
    public function model(): string
    {
        return WorkSchedule::class;
    }

    public function boot(): void
    {
        $this->pushCriteria(app(RequestCriteria::class));
    }
}
