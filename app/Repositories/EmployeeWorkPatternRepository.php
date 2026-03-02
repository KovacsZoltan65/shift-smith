<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Interfaces\EmployeeWorkPatternRepositoryInterface;
use App\Models\EmployeeWorkPattern;
use App\Services\Cache\CacheVersionService;
use App\Services\CacheService;
use Illuminate\Container\Container as AppContainer;
use Illuminate\Support\Facades\DB;
use Override;
use Prettus\Repository\Criteria\RequestCriteria;
use Prettus\Repository\Eloquent\BaseRepository;

class EmployeeWorkPatternRepository extends BaseRepository implements EmployeeWorkPatternRepositoryInterface
{

    public function __construct(
        AppContainer $app,
        private readonly CacheService $cacheService,
        private readonly CacheVersionService $cacheVersionService
    ) {
        parent::__construct($app);
    }

    private function tagForCompany(int $companyId): string
    {
        return "company:{$companyId}:employee_work_patterns";
    }

    public function listByEmployee(int $employeeId, int $companyId): array
    {
        $needCache = (bool) config('cache.enable_employee_work_patterns', false);

        $queryCallback = function () use ($employeeId, $companyId): array {
            return EmployeeWorkPattern::query()
                ->with('workPattern:id,name')
                ->where('company_id', $companyId)
                ->where('employee_id', $employeeId)
                ->orderByDesc('date_from')
                ->get()
                ->all();
        };

        if (!$needCache) {
            return $queryCallback();
        }

        $version = $this->cacheVersionService->get("company:{$companyId}:employee_work_patterns");
        $key = "v{$version}:employee_{$employeeId}";

        return $this->cacheService->remember(
            tag: $this->tagForCompany($companyId),
            key: $key,
            callback: $queryCallback,
            ttl: (int) config('cache.ttl_fetch', 300)
        );
    }

    public function assign(array $data): EmployeeWorkPattern
    {
        return DB::transaction(function () use ($data): EmployeeWorkPattern {
            /** @var EmployeeWorkPattern $row */
            $row = EmployeeWorkPattern::query()->create($data);
            $this->invalidateAfterWrite((int) $row->company_id);
            return $row->fresh(['workPattern']) ?? $row;
        });
    }

    public function updateAssignment(int $id, int $employeeId, int $companyId, array $data): EmployeeWorkPattern
    {
        return DB::transaction(function () use ($id, $employeeId, $companyId, $data): EmployeeWorkPattern {
            /** @var EmployeeWorkPattern $row */
            $row = EmployeeWorkPattern::query()
                ->where('company_id', $companyId)
                ->where('employee_id', $employeeId)
                ->lockForUpdate()
                ->findOrFail($id);

            $row->fill($data);
            $row->save();
            $row->refresh();
            $this->invalidateAfterWrite($companyId);
            return $row->fresh(['workPattern']) ?? $row;
        });
    }

    public function unassign(int $id, int $employeeId, int $companyId): bool
    {
        return DB::transaction(function () use ($id, $employeeId, $companyId): bool {
            /** @var EmployeeWorkPattern $row */
            $row = EmployeeWorkPattern::query()
                ->where('company_id', $companyId)
                ->where('employee_id', $employeeId)
                ->lockForUpdate()
                ->findOrFail($id);

            $deleted = (bool) $row->delete();
            if ($deleted) {
                $this->invalidateAfterWrite($companyId);
            }

            return $deleted;
        });
    }

    public function hasOverlap(
        int $companyId,
        int $employeeId,
        string $dateFrom,
        ?string $dateTo,
        ?int $ignoreId = null
    ): bool {
        $query = EmployeeWorkPattern::query()
            ->where('company_id', $companyId)
            ->where('employee_id', $employeeId)
            ->when($ignoreId !== null, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->where(function ($q) use ($dateFrom): void {
                $q->whereNull('date_to')
                    ->orWhereDate('date_to', '>=', $dateFrom);
            });

        if ($dateTo !== null) {
            $query->whereDate('date_from', '<=', $dateTo);
        }

        return $query->exists();
    }

    public function findActiveForEmployeeOnDate(int $companyId, int $employeeId, string $date): ?EmployeeWorkPattern
    {
        /** @var EmployeeWorkPattern|null $assignment */
        $assignment = EmployeeWorkPattern::query()
            ->with('workPattern')
            ->where('company_id', $companyId)
            ->where('employee_id', $employeeId)
            ->whereDate('date_from', '<=', $date)
            ->where(function ($query) use ($date): void {
                $query->whereNull('date_to')
                    ->orWhereDate('date_to', '>=', $date);
            })
            ->orderByDesc('date_from')
            ->first();

        return $assignment;
    }

    public function findNextForEmployeeAfterDate(int $companyId, int $employeeId, string $date): ?EmployeeWorkPattern
    {
        /** @var EmployeeWorkPattern|null $assignment */
        $assignment = EmployeeWorkPattern::query()
            ->with('workPattern')
            ->where('company_id', $companyId)
            ->where('employee_id', $employeeId)
            ->whereDate('date_from', '>', $date)
            ->orderBy('date_from')
            ->orderBy('id')
            ->first();

        return $assignment;
    }

    public function closeAssignment(int $id, int $companyId, string $dateTo): EmployeeWorkPattern
    {
        return DB::transaction(function () use ($id, $companyId, $dateTo): EmployeeWorkPattern {
            /** @var EmployeeWorkPattern $row */
            $row = EmployeeWorkPattern::query()
                ->where('company_id', $companyId)
                ->lockForUpdate()
                ->findOrFail($id);

            $row->date_to = $dateTo;
            $row->save();
            $row->refresh();
            $this->invalidateAfterWrite($companyId);

            return $row->fresh(['workPattern']) ?? $row;
        });
    }

    public function createAssignment(
        int $companyId,
        int $employeeId,
        int $workPatternId,
        string $dateFrom,
        ?string $dateTo = null
    ): EmployeeWorkPattern {
        return $this->assign([
            'company_id' => $companyId,
            'employee_id' => $employeeId,
            'work_pattern_id' => $workPatternId,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ]);
    }

    private function invalidateAfterWrite(int $companyId): void
    {
        DB::afterCommit(function () use ($companyId): void {
            $this->cacheVersionService->bump("company:{$companyId}:employee_work_patterns");
        });
    }

    #[Override]
    public function model(): string
    {
        return EmployeeWorkPattern::class;
    }

    public function boot(): void
    {
        $this->pushCriteria(app(RequestCriteria::class));
    }
}
