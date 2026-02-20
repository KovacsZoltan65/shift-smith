<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Interfaces\WorkScheduleAssignmentRepositoryInterface;
use App\Models\WorkScheduleAssignment;
use App\Services\Cache\CacheVersionService;
use App\Services\CacheService;
use Illuminate\Container\Container as AppContainer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Override;
use Prettus\Repository\Criteria\RequestCriteria;
use Prettus\Repository\Eloquent\BaseRepository;

/**
 * WorkScheduleAssignment repository.
 *
 * Schedule-hez kötött kiosztások adatbázis műveleteit valósítja meg
 * tenant-szűréssel és cache verziókezeléssel.
 */
class WorkScheduleAssignmentRepository extends BaseRepository implements WorkScheduleAssignmentRepositoryInterface
{
    private readonly CacheVersionService $cacheVersionService;

    /**
     * @param AppContainer $app
     * @param CacheService $cacheService
     * @param CacheVersionService $cacheVersionService
     */
    public function __construct(
        AppContainer $app,
        private readonly CacheService $cacheService,
        CacheVersionService $cacheVersionService
    ) {
        parent::__construct($app);
        $this->cacheVersionService = $cacheVersionService;
    }

    #[Override]
    public function fetchBySchedule(int $scheduleId, int $companyId, array $filters): LengthAwarePaginator
    {
        $needCache = (bool) config('cache.enable_work_schedule_assignments', false);

        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($filters['per_page'] ?? 10)));
        $term = isset($filters['search']) && $filters['search'] !== null
            ? mb_strtolower(trim((string) $filters['search']), 'UTF-8')
            : null;
        $day = $filters['day'] ?? null;

        $field = in_array($filters['field'] ?? null, WorkScheduleAssignment::SORTABLE, true)
            ? (string) $filters['field']
            : null;

        $order = strtolower((string) ($filters['order'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

        $queryCallback = function () use ($scheduleId, $companyId, $term, $day, $field, $order, $page, $perPage): LengthAwarePaginator {
            $query = WorkScheduleAssignment::query()
                ->with([
                    'employee:id,first_name,last_name',
                    'workShift:id,name,start_time,end_time',
                ])
                ->where('company_id', $companyId)
                ->where('work_schedule_id', $scheduleId)
                ->when($day, fn ($q) => $q->whereDate('day', '=', (string) $day))
                ->when($term, function ($q) use ($term): void {
                    $q->where(function ($inner) use ($term): void {
                        $inner->whereHas('employee', function ($emp) use ($term): void {
                            $emp->whereRaw('LOWER(first_name) like ?', ["%{$term}%"])
                                ->orWhereRaw('LOWER(last_name) like ?', ["%{$term}%"]);
                        })->orWhereHas('workShift', function ($shift) use ($term): void {
                            $shift->whereRaw('LOWER(name) like ?', ["%{$term}%"]);
                        });
                    });
                })
                ->when($field, fn ($q) => $q->orderBy($field, $order))
                ->when(!$field, fn ($q) => $q->orderByDesc('day')->orderByDesc('id'));

            return $query->paginate($perPage, ['*'], 'page', $page);
        };

        if (!$needCache) {
            return $queryCallback();
        }

        $params = [
            'schedule_id' => $scheduleId,
            'company_id' => $companyId,
            'page' => $page,
            'per_page' => $perPage,
            'search' => $term,
            'day' => $day,
            'field' => $field,
            'order' => $order,
        ];
        ksort($params);

        $version = $this->cacheVersionService->get($this->companyNamespace($companyId));
        $key = 'v'.$version.':'.hash('sha256', json_encode($params, JSON_THROW_ON_ERROR));

        /** @var LengthAwarePaginator<int, WorkScheduleAssignment> $result */
        $result = $this->cacheService->remember(
            tag: $this->tagForCompany($companyId),
            key: $key,
            callback: $queryCallback,
            ttl: (int) config('cache.ttl_fetch', 60)
        );

        return $result;
    }

    #[Override]
    public function store(array $data): WorkScheduleAssignment
    {
        return DB::transaction(function () use ($data): WorkScheduleAssignment {
            /** @var WorkScheduleAssignment $assignment */
            $assignment = WorkScheduleAssignment::query()
                ->withTrashed()
                ->firstOrNew([
                    'company_id' => $data['company_id'],
                    'work_schedule_id' => $data['work_schedule_id'],
                    'employee_id' => $data['employee_id'],
                    'day' => $data['day'],
                ]);

            $assignment->fill($data);
            $assignment->save();

            if ($assignment->trashed()) {
                $assignment->restore();
            }

            $assignment->refresh();
            $this->invalidateAfterWrite((int) $assignment->company_id);

            return $assignment;
        });
    }

    #[Override]
    public function updateAssignment(int $id, int $scheduleId, int $companyId, array $data): WorkScheduleAssignment
    {
        return DB::transaction(function () use ($id, $scheduleId, $companyId, $data): WorkScheduleAssignment {
            /** @var WorkScheduleAssignment $assignment */
            $assignment = WorkScheduleAssignment::query()
                ->where('company_id', $companyId)
                ->where('work_schedule_id', $scheduleId)
                ->lockForUpdate()
                ->findOrFail($id);

            $assignment->fill($data);
            $assignment->save();
            $assignment->refresh();

            $this->invalidateAfterWrite($companyId);

            return $assignment;
        });
    }

    #[Override]
    public function destroy(int $id, int $scheduleId, int $companyId): bool
    {
        return DB::transaction(function () use ($id, $scheduleId, $companyId): bool {
            /** @var WorkScheduleAssignment $assignment */
            $assignment = WorkScheduleAssignment::query()
                ->where('company_id', $companyId)
                ->where('work_schedule_id', $scheduleId)
                ->lockForUpdate()
                ->findOrFail($id);

            $deleted = (bool) $assignment->delete();
            $this->invalidateAfterWrite($companyId);

            return $deleted;
        });
    }

    #[Override]
    public function bulkDelete(array $ids, int $scheduleId, int $companyId): int
    {
        return DB::transaction(function () use ($ids, $scheduleId, $companyId): int {
            $deleted = WorkScheduleAssignment::query()
                ->where('company_id', $companyId)
                ->where('work_schedule_id', $scheduleId)
                ->whereIn('id', $ids)
                ->delete();

            $this->invalidateAfterWrite($companyId);

            return (int) $deleted;
        });
    }

    /**
     * Tenant cache tag előállítása.
     */
    private function tagForCompany(int $companyId): string
    {
        return WorkScheduleAssignment::getTag().":company_{$companyId}";
    }

    /**
     * Tenant cache namespace.
     */
    private function companyNamespace(int $companyId): string
    {
        return WorkScheduleAssignment::getTag().".company_{$companyId}";
    }

    /**
     * Cache verzió növelés írási művelet után.
     */
    private function invalidateAfterWrite(int $companyId): void
    {
        DB::afterCommit(function () use ($companyId): void {
            $this->cacheVersionService->bump($this->companyNamespace($companyId));
        });
    }

    #[Override]
    public function model(): string
    {
        return WorkScheduleAssignment::class;
    }

    /**
     * Repository bootstrapping (RequestCriteria).
     *
     * @return void
     */
    public function boot(): void
    {
        $this->pushCriteria(app(RequestCriteria::class));
    }
}
