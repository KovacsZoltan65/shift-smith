<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Interfaces\WorkPatternRepositoryInterface;
use App\Models\EmployeeWorkPattern;
use App\Models\WorkPattern;
use App\Services\Cache\CacheVersionService;
use App\Services\CacheService;
use Illuminate\Container\Container as AppContainer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Override;
use Prettus\Repository\Criteria\RequestCriteria;
use Prettus\Repository\Eloquent\BaseRepository;

class WorkPatternRepository extends BaseRepository implements WorkPatternRepositoryInterface
{
    private const NS_SELECTORS_WORK_PATTERNS = 'selectors.work_patterns';

    public function __construct(
        AppContainer $app,
        private readonly CacheService $cacheService,
        private readonly CacheVersionService $cacheVersionService
    ) {
        parent::__construct($app);
    }

    private function tagForCompany(int $companyId): string
    {
        return "company:{$companyId}:work_patterns";
    }

    public function fetch(Request $request): LengthAwarePaginator
    {
        $needCache = (bool) config('cache.enable_work_patterns', false);

        $page = max(1, (int) $request->integer('page', 1));
        $perPage = min(max(1, (int) $request->integer('per_page', 10)), 100);
        $companyId = (int) $request->integer('company_id');
        $termRaw = trim((string) $request->input('search', ''));
        $term = $termRaw === '' ? null : mb_strtolower($termRaw, 'UTF-8');
        $field = in_array((string) $request->input('field', ''), WorkPattern::SORTABLE, true)
            ? (string) $request->input('field')
            : null;
        $direction = strtolower((string) $request->input('order', 'asc')) === 'desc' ? 'desc' : 'asc';

        $queryCallback = function () use ($companyId, $term, $field, $direction, $perPage, $page): LengthAwarePaginator {
            $query = WorkPattern::query()
                ->select('work_patterns.*')
                ->selectSub(
                    fn ($sub) => $sub
                        ->from('employee_work_patterns')
                        ->selectRaw('COUNT(DISTINCT employee_id)')
                        ->whereColumn('employee_work_patterns.work_pattern_id', 'work_patterns.id'),
                    'employees_count'
                )
                ->where('work_patterns.company_id', $companyId)
                ->when($term, function ($q) use ($term): void {
                    $q->whereRaw('LOWER(name) like ?', ["%{$term}%"]);
                })
                ->when($field, fn ($q) => $q->orderBy($field, $direction))
                ->when(!$field, fn ($q) => $q->orderBy('name'));

            return $query->paginate($perPage, ['*'], 'page', $page);
        };

        if (!$needCache) {
            return $queryCallback();
        }

        $version = $this->cacheVersionService->get("company:{$companyId}:work_patterns");
        $hash = hash('sha256', json_encode([
            'page' => $page,
            'per_page' => $perPage,
            'company_id' => $companyId,
            'search' => $term,
            'field' => $field,
            'order' => $direction,
        ], JSON_THROW_ON_ERROR));

        return $this->cacheService->remember(
            tag: $this->tagForCompany($companyId),
            key: "v{$version}:{$hash}",
            callback: $queryCallback,
            ttl: (int) config('cache.ttl_fetch', 60)
        );
    }

    public function getWorkPattern(int $id, int $companyId): WorkPattern
    {
        /** @var WorkPattern $workPattern */
        $workPattern = WorkPattern::query()
            ->where('company_id', $companyId)
            ->findOrFail($id);

        return $workPattern;
    }

    public function store(array $data): WorkPattern
    {
        return DB::transaction(function () use ($data): WorkPattern {
            /** @var WorkPattern $workPattern */
            $workPattern = WorkPattern::query()->create($data);
            $this->invalidateAfterWrite((int) $workPattern->company_id);
            return $workPattern;
        });
    }

    public function update(array $data, mixed $id): WorkPattern
    {
        return DB::transaction(function () use ($data, $id): WorkPattern {
            $companyId = (int) $data['company_id'];

            /** @var WorkPattern $workPattern */
            $workPattern = WorkPattern::query()
                ->where('company_id', $companyId)
                ->lockForUpdate()
                ->findOrFail($id);

            $workPattern->fill($data);
            $workPattern->save();
            $workPattern->refresh();
            $this->invalidateAfterWrite($companyId);
            return $workPattern;
        });
    }

    public function bulkDelete(array $ids, int $companyId): int
    {
        return DB::transaction(function () use ($ids, $companyId): int {
            $deleted = (int) WorkPattern::query()
                ->where('company_id', $companyId)
                ->whereIn('id', $ids)
                ->delete();

            if ($deleted > 0) {
                $this->invalidateAfterWrite($companyId);
            }

            return $deleted;
        });
    }

    public function destroy(int $id, int $companyId): bool
    {
        return DB::transaction(function () use ($id, $companyId): bool {
            /** @var WorkPattern $workPattern */
            $workPattern = WorkPattern::query()
                ->where('company_id', $companyId)
                ->lockForUpdate()
                ->findOrFail($id);

            $deleted = (bool) $workPattern->delete();
            if ($deleted) {
                $this->invalidateAfterWrite($companyId);
            }

            return $deleted;
        });
    }

    public function getToSelect(int $companyId, bool $onlyActive = true): array
    {
        $needCache = (bool) config('cache.enable_work_pattern_to_select', false);

        $queryCallback = function () use ($companyId, $onlyActive): array {
            return WorkPattern::query()
                ->where('company_id', $companyId)
                ->when($onlyActive, fn ($q) => $q->where('active', true))
                ->select(['id', 'name'])
                ->orderBy('name')
                ->get()
                ->map(static fn (WorkPattern $workPattern): array => [
                    'id' => (int) $workPattern->id,
                    'name' => (string) $workPattern->name,
                ])
                ->values()
                ->all();
        };

        if (!$needCache) {
            return $queryCallback();
        }

        $version = $this->cacheVersionService->get(self::NS_SELECTORS_WORK_PATTERNS);
        $hash = hash('sha256', json_encode([
            'company_id' => $companyId,
            'only_active' => $onlyActive,
        ], JSON_THROW_ON_ERROR));

        return $this->cacheService->remember(
            tag: self::NS_SELECTORS_WORK_PATTERNS,
            key: "v{$version}:{$hash}",
            callback: $queryCallback,
            ttl: (int) config('cache.ttl_fetch', 1800)
        );
    }

    public function getAssignedEmployees(int $workPatternId, int $companyId): array
    {
        return EmployeeWorkPattern::query()
            ->with([
                'employee:id,company_id,first_name,last_name,email,phone',
                'workPattern:id,company_id',
            ])
            ->where('company_id', $companyId)
            ->where('work_pattern_id', $workPatternId)
            ->whereHas('workPattern', fn ($q) => $q->where('company_id', $companyId))
            ->whereHas('employee', fn ($q) => $q->where('company_id', $companyId))
            ->orderBy('employee_id')
            ->get()
            ->map(static fn (EmployeeWorkPattern $assignment): array => [
                'id' => (int) $assignment->id,
                'employee_id' => (int) $assignment->employee_id,
                'name' => trim((string) ($assignment->employee?->last_name ?? '').' '.(string) ($assignment->employee?->first_name ?? '')),
                'email' => $assignment->employee?->email ? (string) $assignment->employee->email : null,
                'phone' => $assignment->employee?->phone ? (string) $assignment->employee->phone : null,
                'date_from' => (string) $assignment->date_from->format('Y-m-d'),
                'date_to' => $assignment->date_to?->format('Y-m-d'),
            ])
            ->values()
            ->all();
    }

    private function invalidateAfterWrite(int $companyId): void
    {
        DB::afterCommit(function () use ($companyId): void {
            $this->cacheVersionService->bump("company:{$companyId}:work_patterns");
            $this->cacheVersionService->bump(self::NS_SELECTORS_WORK_PATTERNS);
        });
    }

    #[Override]
    public function model(): string
    {
        return WorkPattern::class;
    }

    public function boot(): void
    {
        $this->pushCriteria(app(RequestCriteria::class));
    }
}
