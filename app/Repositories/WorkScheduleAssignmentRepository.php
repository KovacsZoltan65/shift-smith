<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Interfaces\WorkScheduleAssignmentRepositoryInterface;
use App\Models\WorkShiftAssignment;
use App\Services\Cache\CacheVersionService;
use Illuminate\Container\Container as AppContainer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Override;
use Prettus\Repository\Criteria\RequestCriteria;
use Prettus\Repository\Eloquent\BaseRepository;

class WorkScheduleAssignmentRepository extends BaseRepository implements WorkScheduleAssignmentRepositoryInterface
{
    public function __construct(
        AppContainer $app,
        private readonly CacheVersionService $cacheVersionService
    ) {
        parent::__construct($app);
    }

    #[Override]
    public function feed(int $companyId, int $scheduleId, array $filters): Collection
    {
        $start = $filters['start'] ?? null;
        $end = $filters['end'] ?? null;
        $employeeIds = $filters['employee_ids'] ?? [];
        $workShiftIds = $filters['work_shift_ids'] ?? [];
        $positionIds = $filters['position_ids'] ?? [];

        return WorkShiftAssignment::query()
            ->with([
                'employee:id,company_id,first_name,last_name,position_id',
                'workShift:id,company_id,name,start_time,end_time',
                'workSchedule:id,company_id,name,date_from,date_to,status',
            ])
            ->where('company_id', $companyId)
            ->where('work_schedule_id', $scheduleId)
            ->when($start !== null, fn ($q) => $q->whereDate('date', '>=', $start))
            ->when($end !== null, fn ($q) => $q->whereDate('date', '<=', $end))
            ->when(!empty($employeeIds), fn ($q) => $q->whereIn('employee_id', $employeeIds))
            ->when(!empty($workShiftIds), fn ($q) => $q->whereIn('work_shift_id', $workShiftIds))
            ->when(!empty($positionIds), fn ($q) => $q->whereHas('employee', fn ($qq) => $qq->whereIn('position_id', $positionIds)))
            ->orderBy('date')
            ->orderBy('employee_id')
            ->get();
    }

    #[Override]
    public function create(array $payload): WorkShiftAssignment
    {
        return DB::transaction(function () use ($payload): WorkShiftAssignment {
            /** @var WorkShiftAssignment $row */
            $row = WorkShiftAssignment::query()->create($payload);
            $this->invalidateAfterWrite((int) $row->company_id);
            return $row->fresh(['employee', 'workShift', 'workSchedule']) ?? $row;
        });
    }

    #[Override]
    public function updateAssignment(int $companyId, int $id, array $payload): WorkShiftAssignment
    {
        return DB::transaction(function () use ($companyId, $id, $payload): WorkShiftAssignment {
            /** @var WorkShiftAssignment $row */
            $row = WorkShiftAssignment::query()
                ->where('company_id', $companyId)
                ->lockForUpdate()
                ->findOrFail($id);

            $row->fill($payload);
            $row->save();
            $row->refresh();

            $this->invalidateAfterWrite($companyId);

            return $row->fresh(['employee', 'workShift', 'workSchedule']) ?? $row;
        });
    }

    #[Override]
    public function deleteAssignment(int $companyId, int $id): bool
    {
        return DB::transaction(function () use ($companyId, $id): bool {
            /** @var WorkShiftAssignment $row */
            $row = WorkShiftAssignment::query()
                ->where('company_id', $companyId)
                ->lockForUpdate()
                ->findOrFail($id);

            $deleted = (bool) $row->delete();

            if ($deleted) {
                $this->invalidateAfterWrite($companyId);
            }

            return $deleted;
        });
    }

    #[Override]
    public function bulkUpsert(
        int $companyId,
        int $workScheduleId,
        int $workShiftId,
        array $employeeIds,
        array $dates
    ): Collection {
        return DB::transaction(function () use ($companyId, $workScheduleId, $workShiftId, $employeeIds, $dates): Collection {
            $rows = collect();

            foreach ($employeeIds as $employeeId) {
                foreach ($dates as $date) {
                    /** @var WorkShiftAssignment $row */
                    $row = WorkShiftAssignment::query()->firstOrNew([
                        'company_id' => $companyId,
                        'employee_id' => (int) $employeeId,
                        'date' => $date,
                    ]);

                    $row->fill([
                        'company_id' => $companyId,
                        'work_schedule_id' => $workScheduleId,
                        'work_shift_id' => $workShiftId,
                        'employee_id' => (int) $employeeId,
                        'date' => $date,
                    ]);
                    $row->save();

                    $rows->push($row);
                }
            }

            $this->invalidateAfterWrite($companyId);

            return WorkShiftAssignment::query()
                ->with(['employee', 'workShift', 'workSchedule'])
                ->whereIn('id', $rows->pluck('id')->all())
                ->orderBy('date')
                ->get();
        });
    }

    #[Override]
    public function existsForEmployeeDate(int $companyId, int $employeeId, string $date, ?int $ignoreId = null): bool
    {
        return WorkShiftAssignment::query()
            ->where('company_id', $companyId)
            ->where('employee_id', $employeeId)
            ->whereDate('date', $date)
            ->when($ignoreId !== null, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();
    }

    #[Override]
    public function findForCompany(int $companyId, int $id): WorkShiftAssignment
    {
        /** @var WorkShiftAssignment $assignment */
        $assignment = WorkShiftAssignment::query()
            ->where('company_id', $companyId)
            ->findOrFail($id);

        return $assignment;
    }

    private function invalidateAfterWrite(int $companyId): void
    {
        DB::afterCommit(function () use ($companyId): void {
            $this->cacheVersionService->bump("company:{$companyId}:work_schedule_assignments");
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
