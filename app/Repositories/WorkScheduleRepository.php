<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Interfaces\WorkScheduleRepositoryInterface;
use App\Repositories\Concerns\TenantScopedRepository;
use App\Models\WorkSchedule;
use App\Services\Cache\CacheNamespaces;
use App\Services\Cache\CacheVersionService;
use App\Services\CacheService;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class WorkScheduleRepository implements WorkScheduleRepositoryInterface
{
    use TenantScopedRepository;

    private const NS_SELECTOR = 'selectors.work_schedules';

    public function __construct(
        private readonly CacheService $cacheService,
        private readonly CacheVersionService $cacheVersionService,
    ) {}

    public function fetch(Request $request): LengthAwarePaginator
    {
        $needCache = (bool) config('cache.enable_work_schedules', false);

        $page = max(1, (int) $request->integer('page', 1));
        $perPage = min(max(1, (int) $request->integer('per_page', 10)), 100);
        $companyId = $this->resolveTenantScopedCompanyId((int) $request->integer('company_id'));
        $termRaw = trim((string) $request->input('search', ''));
        $term = $termRaw === '' ? null : mb_strtolower($termRaw, 'UTF-8');
        $field = \in_array((string) $request->input('field', ''), WorkSchedule::getSortable(), true)
            ? (string) $request->input('field')
            : null;
        $direction = strtolower((string) $request->input('order', 'asc')) === 'desc' ? 'desc' : 'asc';

        $queryCallback = function () use ($companyId, $term, $field, $direction, $perPage, $page): LengthAwarePaginator {
            return WorkSchedule::query()
                ->withCount('assignments as assignments_count')
                ->where('company_id', $companyId)
                ->when($term, function ($query) use ($term): void {
                    $query->where(function ($inner) use ($term): void {
                        $inner->whereRaw('LOWER(name) like ?', ["%{$term}%"])
                            ->orWhereRaw('LOWER(status) like ?', ["%{$term}%"]);
                    });
                })
                ->when($field, fn ($query) => $query->orderBy($field, $direction))
                ->when(!$field, fn ($query) => $query->orderBy('date_from')->orderBy('name'))
                ->paginate($perPage, ['*'], 'page', $page);
        };

        if (! $needCache) {
            return $queryCallback();
        }

        $keyParams = [
            'page' => $page,
            'per_page' => $perPage,
            'company_id' => $companyId,
            'search' => $term,
            'field' => $field,
            'order' => $direction,
        ];
        ksort($keyParams);

        /** @var LengthAwarePaginator<int, WorkSchedule> $paginator */
        $paginator = $this->cacheService->remember(
            tag: $this->companyTag($companyId),
            key: 'v'.$this->cacheVersionService->get($this->companyTag($companyId)).':'.hash('sha256', json_encode($keyParams, JSON_THROW_ON_ERROR)),
            callback: $queryCallback,
            ttl: (int) config('cache.ttl_fetch', 60),
        );

        return $paginator;
    }

    public function store(array $data): WorkSchedule
    {
        $companyId = $this->resolveTenantScopedCompanyId((int) $data['company_id']);
        $data['company_id'] = $companyId;

        return DB::transaction(function () use ($data, $companyId): WorkSchedule {
            /** @var WorkSchedule $workSchedule */
            $workSchedule = WorkSchedule::query()->create($data);
            $this->invalidateAfterWrite($companyId);

            return $workSchedule;
        });
    }

    public function update(array $data, int $id): WorkSchedule
    {
        $companyId = $this->resolveTenantScopedCompanyId((int) $data['company_id']);

        return DB::transaction(function () use ($data, $id, $companyId): WorkSchedule {
            /** @var WorkSchedule $workSchedule */
            $workSchedule = WorkSchedule::query()
                ->where('company_id', $companyId)
                ->lockForUpdate()
                ->findOrFail($id);

            $workSchedule->fill($data);
            $workSchedule->save();
            $workSchedule->refresh();
            $this->invalidateAfterWrite($companyId);

            return $workSchedule;
        });
    }

    public function getWorkSchedule(int $id, int $companyId): WorkSchedule
    {
        $scopedCompanyId = $this->resolveTenantScopedCompanyId($companyId);

        /** @var WorkSchedule $workSchedule */
        $workSchedule = WorkSchedule::query()
            ->withCount('assignments as assignments_count')
            ->where('company_id', $scopedCompanyId)
            ->findOrFail($id);

        return $workSchedule;
    }

    public function destroy(int $id, int $companyId): bool
    {
        $scopedCompanyId = $this->resolveTenantScopedCompanyId($companyId);

        return DB::transaction(function () use ($id, $scopedCompanyId): bool {
            /** @var WorkSchedule $workSchedule */
            $workSchedule = WorkSchedule::query()
                ->where('company_id', $scopedCompanyId)
                ->lockForUpdate()
                ->findOrFail($id);

            $deleted = (bool) $workSchedule->delete();
            if ($deleted) {
                $this->invalidateAfterWrite($scopedCompanyId);
            }

            return $deleted;
        });
    }

    public function bulkDelete(array $ids, int $companyId): int
    {
        $scopedCompanyId = $this->resolveTenantScopedCompanyId($companyId);

        return DB::transaction(function () use ($ids, $scopedCompanyId): int {
            $deleted = (int) WorkSchedule::query()
                ->where('company_id', $scopedCompanyId)
                ->whereIn('id', $ids)
                ->delete();

            if ($deleted > 0) {
                $this->invalidateAfterWrite($scopedCompanyId);
            }

            return $deleted;
        });
    }

    public function selector(int $companyId, bool $onlyPublished = false): array
    {
        $needCache = (bool) config('cache.enable_work_schedules', false);
        $scopedCompanyId = $this->resolveTenantScopedCompanyId($companyId);
        $keyParams = [
            'company_id' => $scopedCompanyId,
            'only_published' => $onlyPublished,
        ];

        $queryCallback = function () use ($scopedCompanyId, $onlyPublished): array {
            return WorkSchedule::query()
                ->where('company_id', $scopedCompanyId)
                ->when($onlyPublished, fn ($query) => $query->where('status', 'published'))
                ->orderBy('date_from')
                ->orderBy('name')
                ->get(['id', 'company_id', 'name', 'date_from', 'date_to', 'status'])
                ->map(static fn (WorkSchedule $schedule): array => [
                    'id' => (int) $schedule->id,
                    'company_id' => (int) $schedule->company_id,
                    'name' => (string) $schedule->name,
                    'date_from' => $schedule->date_from instanceof CarbonInterface
                        ? $schedule->date_from->format('Y-m-d')
                        : (string) $schedule->date_from,
                    'date_to' => $schedule->date_to instanceof CarbonInterface
                        ? $schedule->date_to->format('Y-m-d')
                        : (string) $schedule->date_to,
                    'status' => (string) $schedule->status,
                ])
                ->values()
                ->all();
        };

        if (! $needCache) {
            return $queryCallback();
        }

        $tenantNamespace = TenantGroup::current()?->id !== null
            ? CacheNamespaces::tenantWorkSchedules((int) TenantGroup::current()->id)
            : self::NS_SELECTOR;

        /** @var array<int, array{id:int, company_id:int, name:string, date_from:string, date_to:string, status:string}> $items */
        $items = $this->cacheService->remember(
            tag: $tenantNamespace,
            key: 'v'.$this->cacheVersionService->get(self::NS_SELECTOR).':'.hash('sha256', json_encode($keyParams, JSON_THROW_ON_ERROR)),
            callback: $queryCallback,
            ttl: (int) config('cache.ttl_fetch', 1800),
        );

        return $items;
    }

    private function companyTag(int $companyId): string
    {
        return "company:{$companyId}:work_schedules";
    }

    private function invalidateAfterWrite(int $companyId): void
    {
        DB::afterCommit(function () use ($companyId): void {
            $this->cacheVersionService->bump($this->companyTag($companyId));
            $this->cacheVersionService->bump(self::NS_SELECTOR);
        });
    }
}
