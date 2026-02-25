<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Interfaces\WorkShiftAssignmentRepositoryInterface;
use App\Models\WorkShiftAssignment;
use App\Services\Cache\CacheNamespaces;
use App\Services\Cache\CacheVersionService;
use App\Services\CacheService;
use App\Services\TenantContext;
use Illuminate\Container\Container as AppContainer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Override;
use Prettus\Repository\Criteria\RequestCriteria;
use Prettus\Repository\Eloquent\BaseRepository;

class WorkShiftAssignmentRepository extends BaseRepository implements WorkShiftAssignmentRepositoryInterface
{
    public function __construct(
        AppContainer $app,
        private readonly CacheService $cacheService,
        private readonly CacheVersionService $cacheVersionService,
        private readonly TenantContext $tenantContext
    ) {
        parent::__construct($app);
    }

    /**
     * @return Collection<int, WorkShiftAssignment>
     */
    public function listByWorkShift(int $workShiftId): Collection
    {
        return WorkShiftAssignment::query()
            ->with(['employee:id,first_name,last_name', 'workSchedule:id,name'])
            ->where('work_shift_id', $workShiftId)
            ->orderByDesc('date')
            ->get();
    }

    public function upsertByEmployeeAndDate(
        int $companyId,
        int $workShiftId,
        int $workScheduleId,
        int $employeeId,
        string $date
    ): WorkShiftAssignment {
        return DB::transaction(function () use ($companyId, $workShiftId, $workScheduleId, $employeeId, $date): WorkShiftAssignment {
            /** @var WorkShiftAssignment $assignment */
            $assignment = WorkShiftAssignment::query()->firstOrNew([
                'company_id' => $companyId,
                'employee_id' => $employeeId,
                'date' => $date,
            ]);

            $assignment->fill([
                'company_id' => $companyId,
                'work_schedule_id' => $workScheduleId,
                'work_shift_id' => $workShiftId,
                'employee_id' => $employeeId,
                'date' => $date,
            ]);
            $assignment->save();

            $this->invalidateAfterWrite();

            return $assignment->fresh(['employee:id,first_name,last_name', 'workSchedule:id,name']) ?? $assignment;
        });
    }

    public function deleteForWorkShift(int $workShiftId, int $id): bool
    {
        return DB::transaction(function () use ($workShiftId, $id): bool {
            /** @var WorkShiftAssignment $assignment */
            $assignment = WorkShiftAssignment::query()
                ->where('work_shift_id', $workShiftId)
                ->lockForUpdate()
                ->findOrFail($id);

            $deleted = (bool) $assignment->delete();
            if ($deleted) {
                $this->invalidateAfterWrite();
            }

            return $deleted;
        });
    }

    private function invalidateAfterWrite(): void
    {
        DB::afterCommit(function (): void {
            $tenantGroupId = $this->tenantContext->currentTenantGroupIdOrFail();
            $namespace = CacheNamespaces::tenantWorkScheduleAssignments($tenantGroupId);
            $this->cacheVersionService->bump($namespace);
        });
    }

    #[Override]
    public function model(): string
    {
        return WorkShiftAssignment::class;
    }

    public function boot(): void
    {
        $this->pushCriteria(app(RequestCriteria::class));
    }
}
