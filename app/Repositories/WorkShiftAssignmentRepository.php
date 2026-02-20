<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Interfaces\WorkShiftAssignmentRepositoryInterface;
use App\Models\WorkShiftAssignment;
use App\Services\Cache\CacheVersionService;
use App\Services\CacheService;
use Illuminate\Container\Container as AppContainer;
use Illuminate\Support\Facades\DB;
use Override;
use Prettus\Repository\Criteria\RequestCriteria;
use Prettus\Repository\Eloquent\BaseRepository;

/**
 * Legacy/v1 WorkShiftAssignment repository.
 *
 * A történeti WorkShift-hozzárendelés végpontokat szolgálja ki
 * tenant-szűréssel és cache verziókezeléssel.
 */
class WorkShiftAssignmentRepository extends BaseRepository implements WorkShiftAssignmentRepositoryInterface
{
    private const NS_WORK_SHIFT_ASSIGNMENTS = 'work_shift_assignments.company_';

    /**
     * @param AppContainer $app
     * @param CacheService $cacheService
     * @param CacheVersionService $cacheVersionService
     */
    public function __construct(
        AppContainer $app,
        private readonly CacheService $cacheService,
        private readonly CacheVersionService $cacheVersionService
    ) {
        parent::__construct($app);
    }

    #[Override]
    public function listByShift(int $workShiftId, int $companyId): array
    {
        $needCache = (bool) config('cache.enable_work_shift_assignments', false);

        $queryCallback = function () use ($workShiftId, $companyId): array {
            /** @var list<WorkShiftAssignment> $rows */
            $rows = WorkShiftAssignment::query()
                ->with('employee:id,first_name,last_name')
                ->where('company_id', $companyId)
                ->where('work_shift_id', $workShiftId)
                ->orderByDesc('day')
                ->get()
                ->all();

            return $rows;
        };

        if (!$needCache) {
            return $queryCallback();
        }

        $version = $this->cacheVersionService->get(self::NS_WORK_SHIFT_ASSIGNMENTS.$companyId);
        $key = "v{$version}:shift_{$workShiftId}";

        /** @var list<WorkShiftAssignment> $rows */
        $rows = $this->cacheService->remember(
            tag: "work_shift_assignments:company_{$companyId}",
            key: $key,
            callback: $queryCallback,
            ttl: (int) config('cache.ttl_fetch', 300)
        );

        return $rows;
    }

    #[Override]
    public function store(array $data): WorkShiftAssignment
    {
        return DB::transaction(function () use ($data): WorkShiftAssignment {
            /** @var WorkShiftAssignment $assignment */
            $assignment = WorkShiftAssignment::query()
                ->withTrashed()
                ->firstOrNew([
                    'company_id' => $data['company_id'],
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
    public function destroy(int $id, int $workShiftId, int $companyId): bool
    {
        return DB::transaction(function () use ($id, $workShiftId, $companyId): bool {
            /** @var WorkShiftAssignment $assignment */
            $assignment = WorkShiftAssignment::query()
                ->where('company_id', $companyId)
                ->where('work_shift_id', $workShiftId)
                ->lockForUpdate()
                ->findOrFail($id);

            $deleted = (bool) $assignment->delete();
            $this->invalidateAfterWrite($companyId);

            return $deleted;
        });
    }

    /**
     * Cache invalidálás írási művelet után.
     */
    private function invalidateAfterWrite(int $companyId): void
    {
        DB::afterCommit(function () use ($companyId): void {
            $this->cacheVersionService->bump(self::NS_WORK_SHIFT_ASSIGNMENTS.$companyId);
        });
    }

    #[Override]
    public function model(): string
    {
        return WorkShiftAssignment::class;
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
