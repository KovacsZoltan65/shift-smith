<?php

declare(strict_types=1);

namespace App\Services;

use App\Interfaces\WorkShiftAssignmentRepositoryInterface;
use App\Models\Employee;
use App\Models\WorkSchedule;
use App\Models\WorkShift;
use App\Models\WorkShiftAssignment;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class WorkShiftAssignmentService
{
    public function __construct(
        private readonly WorkShiftAssignmentRepositoryInterface $repo
    ) {}

    /**
     * @return Collection<int, WorkShiftAssignment>
     */
    public function listByWorkShift(int $workShiftId): Collection
    {
        return $this->repo->listByWorkShift($workShiftId);
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
        $shift = WorkShift::query()->findOrFail($workShiftId);
        $employee = Employee::query()->findOrFail((int) $payload['employee_id']);
        $date = (string) $payload['date'];

        $workSchedule = $this->resolveWorkSchedule($shift, $payload, $date);
        $this->validateCompanyConsistency($shift, $employee, $workSchedule);
        $this->validateDateInScheduleRange($workSchedule, $date);

        return $this->repo->upsertByEmployeeAndDate(
            companyId: (int) $shift->company_id,
            workShiftId: (int) $shift->id,
            workScheduleId: (int) $workSchedule->id,
            employeeId: (int) $employee->id,
            date: $date
        );
    }

    public function unassign(int $workShiftId, int $id): bool
    {
        return $this->repo->deleteForWorkShift($workShiftId, $id);
    }

    private function resolveWorkSchedule(WorkShift $shift, array $payload, string $date): WorkSchedule
    {
        if (isset($payload['work_schedule_id']) && (int) $payload['work_schedule_id'] > 0) {
            return WorkSchedule::query()->findOrFail((int) $payload['work_schedule_id']);
        }

        /** @var WorkSchedule|null $schedule */
        $schedule = WorkSchedule::query()
            ->where('company_id', $shift->company_id)
            ->whereDate('date_from', '<=', $date)
            ->whereDate('date_to', '>=', $date)
            ->orderBy('id')
            ->first();

        if ($schedule === null) {
            throw ValidationException::withMessages([
                'work_schedule_id' => 'A megadott dátumhoz nem található munkabeosztás.',
            ]);
        }

        return $schedule;
    }

    private function validateCompanyConsistency(WorkShift $shift, Employee $employee, WorkSchedule $schedule): void
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

        if ((int) $schedule->company_id !== $companyId) {
            throw ValidationException::withMessages([
                'work_schedule_id' => 'A beosztás és a műszak cége nem egyezik.',
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
