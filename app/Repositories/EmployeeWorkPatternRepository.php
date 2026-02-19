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

/**
 * Dolgozó-munkarend hozzárendelés repository osztály.
 *
 * Adatbázis műveletek kezelése a hozzárendelésekhez cache támogatással.
 */
class EmployeeWorkPatternRepository extends BaseRepository implements EmployeeWorkPatternRepositoryInterface
{
    protected CacheService $cacheService;

    private readonly CacheVersionService $cacheVersionService;

    private const NS_EMPLOYEE_WORK_PATTERNS_LIST = 'employee_work_patterns.list';

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
        return "employee_work_patterns:company_{$companyId}";
    }

    /**
     * @inheritDoc
     */
    public function listByEmployee(int $employeeId, int $companyId): array
    {
        $needCache = (bool) config('cache.enable_employee_work_patterns', false);
        $queryCallback = function () use ($employeeId, $companyId): array {
            /** @var list<EmployeeWorkPattern> $rows */
            $rows = EmployeeWorkPattern::query()
                ->with('workPattern:id,name,type')
                ->where('company_id', $companyId)
                ->where('employee_id', $employeeId)
                ->orderByDesc('date_from')
                ->get()
                ->all();
            return $rows;
        };

        if (!$needCache || $companyId <= 0) {
            return $queryCallback();
        }

        $version = $this->cacheVersionService->get(self::NS_EMPLOYEE_WORK_PATTERNS_LIST . ".company_{$companyId}");
        $key = "v{$version}:employee_{$employeeId}";

        /** @var list<EmployeeWorkPattern> */
        return $this->cacheService->remember(
            tag: $this->tagForCompany($companyId),
            key: $key,
            callback: $queryCallback,
            ttl: (int) config('cache.ttl_fetch', 300)
        );
    }

    /**
     * @inheritDoc
     */
    public function assign(array $data): EmployeeWorkPattern
    {
        return DB::transaction(function () use ($data): EmployeeWorkPattern {
            /** @var EmployeeWorkPattern $row */
            $row = EmployeeWorkPattern::query()->create($data);
            $this->invalidateAfterWrite((int) $row->company_id);
            return $row->fresh(['workPattern']) ?? $row;
        });
    }

    /**
     * @inheritDoc
     */
    public function updateAssignment(int $id, int $employeeId, array $data): EmployeeWorkPattern
    {
        return DB::transaction(function () use ($id, $employeeId, $data): EmployeeWorkPattern {
            /** @var EmployeeWorkPattern $row */
            $row = EmployeeWorkPattern::query()
                ->where('employee_id', $employeeId)
                ->lockForUpdate()
                ->findOrFail($id);

            $row->fill($data);
            $row->save();
            $row->refresh();

            $this->invalidateAfterWrite((int) $row->company_id);
            return $row->fresh(['workPattern']) ?? $row;
        });
    }

    /**
     * @inheritDoc
     */
    public function unassign(int $id, int $employeeId): bool
    {
        return DB::transaction(function () use ($id, $employeeId): bool {
            /** @var EmployeeWorkPattern $row */
            $row = EmployeeWorkPattern::query()
                ->where('employee_id', $employeeId)
                ->lockForUpdate()
                ->findOrFail($id);

            $companyId = (int) $row->company_id;
            $deleted = (bool) $row->delete();
            $this->invalidateAfterWrite($companyId);
            return $deleted;
        });
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
            $this->cacheVersionService->bump(self::NS_EMPLOYEE_WORK_PATTERNS_LIST . ".company_{$companyId}");
        });
    }

    /**
     * Repository model osztály megadása.
     */
    #[Override]
    public function model(): string
    {
        return EmployeeWorkPattern::class;
    }

    /**
     * Repository inicializálás.
     */
    public function boot(): void
    {
        $this->pushCriteria(app(RequestCriteria::class));
    }
}
