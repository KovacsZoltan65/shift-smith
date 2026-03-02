<?php

declare(strict_types=1);

namespace App\Services;

use App\Interfaces\WorkShiftAssignmentRepositoryInterface;
use App\Models\WorkPattern;
use App\Models\Employee;
use App\Models\WorkSchedule;
use App\Models\WorkShift;
use App\Models\WorkShiftAssignment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkShiftAssignmentService
{
    public function __construct(
        private readonly WorkShiftAssignmentRepositoryInterface $repo,
        private readonly EmployeeWorkPatternService $employeeWorkPatternService,
        private readonly WorkPatternService $workPatternService,
        private readonly WorkScheduleResolverService $workScheduleResolverService
    ) {}

    /**
     * @return Collection<int, WorkShiftAssignment>
     */
    public function listByWorkShift(int $workShiftId): Collection
    {
        return $this->repo->listByWorkShift($workShiftId)
            ->map(function (WorkShiftAssignment $assignment): WorkShiftAssignment {
                $pattern = $this->employeeWorkPatternService->findActiveForEmployeeOnDate(
                    (int) $assignment->employee_id,
                    (int) $assignment->company_id,
                    (string) $assignment->date->format('Y-m-d')
                );

                $assignment->setAttribute('work_pattern_name', $pattern?->workPattern?->name);

                return $assignment;
            });
    }

    /**
     * @return Collection<int, array{id:int,name:string,date_from:string,date_to:string,status:?string}>
     */
    public function getSchedulesForWorkShift(int $workShiftId): Collection
    {
        $shift = WorkShift::query()->findOrFail($workShiftId);

        return $this->repo->getSchedulesForCompany((int) $shift->company_id)
            ->map(static fn (WorkSchedule $schedule): array => [
                'id' => (int) $schedule->id,
                'name' => (string) $schedule->name,
                'date_from' => (string) $schedule->date_from->format('Y-m-d'),
                'date_to' => (string) $schedule->date_to->format('Y-m-d'),
                'status' => $schedule->status ? (string) $schedule->status : null,
            ])
            ->values();
    }

    public function assign(int $workShiftId, array $payload): WorkShiftAssignment
    {
        return DB::transaction(function () use ($workShiftId, $payload): WorkShiftAssignment {
            $shift = WorkShift::query()->findOrFail($workShiftId);
            $employee = Employee::query()->findOrFail((int) $payload['employee_id']);
            $workPattern = $this->resolveWorkPattern((int) $shift->company_id, (int) $payload['work_pattern_id']);
            $date = (string) $payload['date'];

            $this->validateCompanyConsistency($shift, $employee);

            $workScheduleId = $this->workScheduleResolverService->resolveForCompanyAndPattern(
                (int) $shift->company_id,
                (int) $workPattern->id
            );

            $workSchedule = WorkSchedule::query()->findOrFail($workScheduleId);
            $this->validateDateInScheduleRange($workSchedule, $date);

            $assignment = $this->repo->upsertByEmployeeAndDate(
                companyId: (int) $shift->company_id,
                workShiftId: (int) $shift->id,
                workScheduleId: (int) $workSchedule->id,
                employeeId: (int) $employee->id,
                date: $date
            );

            $this->employeeWorkPatternService->ensureAssignmentForDate(
                (int) $employee->id,
                (int) $shift->company_id,
                (int) $workPattern->id,
                $date
            );

            $assignment->setAttribute('work_pattern_name', $workPattern->name);

            return $assignment;
        });
    }

    public function unassign(int $workShiftId, int $id): bool
    {
        return $this->repo->deleteForWorkShift($workShiftId, $id);
    }

    private function resolveWorkPattern(int $companyId, int $workPatternId): WorkPattern
    {
        return $this->workPatternService->find($workPatternId, $companyId);
    }

    private function validateCompanyConsistency(WorkShift $shift, Employee $employee): void
    {
        $companyId = (int) $shift->company_id;
        $employeeInCompany = $employee->companies()
            ->where('companies.id', $companyId)
            ->where('companies.active', true)
            ->where('company_employee.active', true)
            ->exists();

        if (! $employeeInCompany) {
            throw ValidationException::withMessages([
                'employee_id' => 'A dolgozó és a műszak cége nem egyezik.',
            ]);
        }
    }

    private function validateDateInScheduleRange(WorkSchedule $schedule, string $date): void
    {
        $from = (string) $schedule->date_from->format('Y-m-d');
        $to = (string) $schedule->date_to->format('Y-m-d');

        if ($date < $from || $date > $to) {
            throw ValidationException::withMessages([
                'date' => 'A dátumnak a beosztás intervallumába kell esnie.',
            ]);
        }
    }
}
