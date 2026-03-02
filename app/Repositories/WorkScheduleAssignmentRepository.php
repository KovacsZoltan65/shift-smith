<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Interfaces\WorkScheduleAssignmentRepositoryInterface;
use App\Models\Employee;
use App\Models\WorkSchedule;
use App\Models\WorkShift;
use App\Models\WorkShiftAssignment;
use App\Services\Cache\CacheNamespaces;
use App\Services\Cache\CacheVersionService;
use App\Services\TenantContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Override;

final class WorkScheduleAssignmentRepository implements WorkScheduleAssignmentRepositoryInterface
{
    public function __construct(
        private readonly CacheVersionService $cacheVersionService,
        private readonly TenantContext $tenantContext
    ) {}

    #[Override]
    public function paginate(int $companyId, array $filters): LengthAwarePaginator
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = min(max((int) ($filters['per_page'] ?? 10), 1), 100);
        $field = (string) ($filters['field'] ?? 'id');
        $direction = strtolower((string) ($filters['order'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowedFields = ['id', 'date', 'employee_id', 'work_shift_id', 'work_schedule_id', 'created_at'];

        if (! \in_array($field, $allowedFields, true)) {
            $field = 'id';
        }

        $query = WorkShiftAssignment::query()
            ->with([
                'employee:id,company_id,first_name,last_name,position_id',
                'workShift:id,company_id,name,start_time,end_time',
                'workSchedule:id,company_id,name,date_from,date_to,status',
            ])
            ->where('company_id', $companyId)
            ->when(! empty($filters['search']), function ($q) use ($filters): void {
                $term = mb_strtolower(trim((string) $filters['search']), 'UTF-8');
                if ($term === '') {
                    return;
                }

                $q->where(function ($qq) use ($term): void {
                    $qq->whereHas('employee', function ($e) use ($term): void {
                        $e->whereRaw("LOWER(CONCAT(last_name, ' ', first_name)) like ?", ["%{$term}%"]);
                    })->orWhereHas('workShift', fn ($s) => $s->whereRaw('LOWER(name) like ?', ["%{$term}%"]));
                });
            })
            ->when(! empty($filters['schedule_id']), fn ($q) => $q->where('work_schedule_id', (int) $filters['schedule_id']))
            ->when(! empty($filters['employee_id']), fn ($q) => $q->where('employee_id', (int) $filters['employee_id']))
            ->when(! empty($filters['work_shift_id']), fn ($q) => $q->where('work_shift_id', (int) $filters['work_shift_id']))
            ->when(! empty($filters['date_from']), fn ($q) => $q->whereDate('date', '>=', (string) $filters['date_from']))
            ->when(! empty($filters['date_to']), fn ($q) => $q->whereDate('date', '<=', (string) $filters['date_to']))
            ->orderBy($field, $direction);

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    #[Override]
    public function fetch(int $companyId, array $filters): Collection
    {
        return $this->paginate($companyId, [...$filters, 'per_page' => 100, 'page' => 1])->getCollection();
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
    public function findOrFailScoped(int $id, int $companyId): WorkShiftAssignment
    {
        /** @var WorkShiftAssignment $assignment */
        $assignment = WorkShiftAssignment::query()
            ->where('company_id', $companyId)
            ->findOrFail($id);

        return $assignment;
    }

    #[Override]
    public function store(int $companyId, array $payload): WorkShiftAssignment
    {
        return DB::transaction(function () use ($companyId, $payload): WorkShiftAssignment {
            /** @var WorkShiftAssignment $row */
            $row = WorkShiftAssignment::query()->create([
                ...$payload,
                'company_id' => $companyId,
            ]);
            $this->invalidateAfterWrite();
            return $row->fresh(['employee', 'workShift', 'workSchedule']) ?? $row;
        });
    }

    #[Override]
    public function update(WorkShiftAssignment $assignment, array $payload): WorkShiftAssignment
    {
        return DB::transaction(function () use ($assignment, $payload): WorkShiftAssignment {
            /** @var WorkShiftAssignment $row */
            $row = WorkShiftAssignment::query()
                ->where('company_id', (int) $assignment->company_id)
                ->lockForUpdate()
                ->findOrFail($assignment->id);

            $row->fill($payload);
            $row->save();
            $row->refresh();

            $this->invalidateAfterWrite();

            return $row->fresh(['employee', 'workShift', 'workSchedule']) ?? $row;
        });
    }

    #[Override]
    public function delete(WorkShiftAssignment $assignment): bool
    {
        return DB::transaction(function () use ($assignment): bool {
            /** @var WorkShiftAssignment $row */
            $row = WorkShiftAssignment::query()
                ->where('company_id', (int) $assignment->company_id)
                ->lockForUpdate()
                ->findOrFail($assignment->id);

            $deleted = (bool) $row->delete();

            if ($deleted) {
                $this->invalidateAfterWrite();
            }

            return $deleted;
        });
    }

    #[Override]
    public function bulkDelete(array $ids, int $companyId): int
    {
        return DB::transaction(function () use ($ids, $companyId): int {
            $deleted = (int) WorkShiftAssignment::query()
                ->where('company_id', $companyId)
                ->whereIn('id', $ids)
                ->delete();

            if ($deleted > 0) {
                $this->invalidateAfterWrite();
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

            $this->invalidateAfterWrite();

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
    public function getSchedulesForSelector(int $companyId): Collection
    {
        return WorkSchedule::query()
            ->where('company_id', $companyId)
            ->orderByDesc('date_from')
            ->get(['id', 'company_id', 'name', 'date_from', 'date_to', 'status']);
    }

    #[Override]
    public function findScheduleForCompany(int $companyId, int $scheduleId): WorkSchedule
    {
        return $this->findScheduleOrFailScoped($scheduleId, $companyId);
    }

    #[Override]
    public function findEmployeeForCompany(int $companyId, int $employeeId): Employee
    {
        return $this->findEmployeeOrFailScoped($employeeId, $companyId);
    }

    #[Override]
    public function findShiftForCompany(int $companyId, int $workShiftId): WorkShift
    {
        return $this->findShiftOrFailScoped($workShiftId, $companyId);
    }

    #[Override]
    public function employeesBelongToCompany(int $companyId, array $employeeIds): bool
    {
        if ($employeeIds === []) {
            return true;
        }

        return $this->countEmployeesScoped($employeeIds, $companyId) === count($employeeIds);
    }

    #[Override]
    public function shiftsBelongToCompany(int $companyId, array $shiftIds): bool
    {
        if ($shiftIds === []) {
            return true;
        }

        return $this->countShiftsScoped($shiftIds, $companyId) === count($shiftIds);
    }

    #[Override]
    public function findScheduleOrFailScoped(int $scheduleId, int $companyId): WorkSchedule
    {
        /** @var WorkSchedule $schedule */
        $schedule = WorkSchedule::query()
            ->where('company_id', $companyId)
            ->findOrFail($scheduleId);

        return $schedule;
    }

    #[Override]
    public function findEmployeeOrFailScoped(int $employeeId, int $companyId): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::query()
            ->where('company_id', $companyId)
            ->findOrFail($employeeId);

        return $employee;
    }

    #[Override]
    public function findShiftOrFailScoped(int $workShiftId, int $companyId): WorkShift
    {
        /** @var WorkShift $shift */
        $shift = WorkShift::query()
            ->where('company_id', $companyId)
            ->findOrFail($workShiftId);

        return $shift;
    }

    #[Override]
    public function countEmployeesScoped(array $employeeIds, int $companyId): int
    {
        if ($employeeIds === []) {
            return 0;
        }

        return Employee::query()
            ->where('company_id', $companyId)
            ->whereIn('id', $employeeIds)
            ->count();
    }

    #[Override]
    public function countShiftsScoped(array $shiftIds, int $companyId): int
    {
        if ($shiftIds === []) {
            return 0;
        }

        return WorkShift::query()
            ->where('company_id', $companyId)
            ->whereIn('id', $shiftIds)
            ->count();
    }

    private function invalidateAfterWrite(): void
    {
        DB::afterCommit(function (): void {
            $tenantGroupId = $this->tenantContext->currentTenantGroupIdOrFail();
            $namespace = CacheNamespaces::tenantWorkScheduleAssignments($tenantGroupId);
            $this->cacheVersionService->bump($namespace);
        });
    }

}
