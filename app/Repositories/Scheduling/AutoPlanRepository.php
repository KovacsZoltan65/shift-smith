<?php

declare(strict_types=1);

namespace App\Repositories\Scheduling;

use App\Models\Employee;
use App\Models\GenerationReport;
use App\Models\WorkSchedule;
use App\Models\WorkShift;
use App\Models\WorkShiftAssignment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AutoPlanRepository
{
    /**
     * @param list<int> $employeeIds
     * @return Collection<int, Employee>
     */
    public function employeesByCompany(int $companyId, array $employeeIds): Collection
    {
        return Employee::query()
            ->where('company_id', $companyId)
            ->whereIn('id', $employeeIds)
            ->orderBy('id')
            ->get(['id', 'company_id', 'first_name', 'last_name', 'active']);
    }

    /**
     * @param list<int> $shiftIds
     * @return Collection<int, WorkShift>
     */
    public function shiftsByCompany(int $companyId, array $shiftIds): Collection
    {
        return WorkShift::query()
            ->where('company_id', $companyId)
            ->whereIn('id', $shiftIds)
            ->get(['id', 'company_id', 'name', 'start_time', 'end_time']);
    }

    /**
     * @param list<int> $employeeIds
     * @return Collection<int, WorkShiftAssignment>
     */
    public function assignmentsForEmployeesBetween(
        int $companyId,
        array $employeeIds,
        string $dateFrom,
        string $dateTo
    ): Collection {
        return WorkShiftAssignment::query()
            ->with(['workShift:id,company_id,name,start_time,end_time'])
            ->where('company_id', $companyId)
            ->whereIn('employee_id', $employeeIds)
            ->whereDate('date', '>=', $dateFrom)
            ->whereDate('date', '<=', $dateTo)
            ->orderBy('date')
            ->get();
    }

    public function createDraftSchedule(int $companyId, string $month, string $dateFrom, string $dateTo): WorkSchedule
    {
        /** @var WorkSchedule $schedule */
        $schedule = WorkSchedule::query()->create([
            'company_id' => $companyId,
            'name' => sprintf('AutoPlan %s', $month),
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'status' => 'draft',
        ]);

        return $schedule;
    }

    /**
     * @param list<array{
     *   company_id:int,
     *   work_schedule_id:int,
     *   employee_id:int,
     *   work_shift_id:int,
     *   date:string,
     *   created_at:string,
     *   updated_at:string
     * }> $rows
     */
    public function insertAssignments(array $rows): int
    {
        if ($rows === []) {
            return 0;
        }

        foreach (array_chunk($rows, 1000) as $chunk) {
            DB::table('work_shift_assignments')->insert($chunk);
        }

        return count($rows);
    }

    /**
     * @param array<string, mixed> $inputJson
     * @param array<string, mixed> $resultJson
     */
    public function createGenerationReport(
        int $companyId,
        int $workScheduleId,
        array $inputJson,
        array $resultJson,
        int $createdBy
    ): GenerationReport {
        /** @var GenerationReport $report */
        $report = GenerationReport::query()->create([
            'company_id' => $companyId,
            'work_schedule_id' => $workScheduleId,
            'input_json' => $inputJson,
            'result_json' => $resultJson,
            'created_by' => $createdBy,
        ]);

        return $report;
    }

    /**
     * @return array{
     *   min_rest_hours:int,
     *   max_consecutive_days:int,
     *   weekend_fairness:bool
     * }
     */
    public function getAutoPlanRules(): array
    {
        return [
            'min_rest_hours' => $this->resolveAppInt('autoplan.min_rest_hours', 11),
            'max_consecutive_days' => $this->resolveAppInt('autoplan.max_consecutive_days', 6),
            'weekend_fairness' => $this->resolveAppBool('autoplan.weekend_fairness', true),
        ];
    }

    /**
     * @param array{
     *   min_rest_hours:int,
     *   max_consecutive_days:int,
     *   weekend_fairness:bool
     * } $rules
     */
    public function persistAutoPlanRules(array $rules): void
    {
        if (!Schema::hasTable('app_settings')) {
            return;
        }

        DB::table('app_settings')->updateOrInsert(
            ['key' => 'autoplan.min_rest_hours'],
            ['value' => (string) (int) $rules['min_rest_hours']]
        );
        DB::table('app_settings')->updateOrInsert(
            ['key' => 'autoplan.max_consecutive_days'],
            ['value' => (string) (int) $rules['max_consecutive_days']]
        );
        DB::table('app_settings')->updateOrInsert(
            ['key' => 'autoplan.weekend_fairness'],
            ['value' => $rules['weekend_fairness'] ? '1' : '0']
        );
    }

    private function resolveAppInt(string $key, int $default): int
    {
        if (!Schema::hasTable('app_settings')) {
            return $default;
        }

        $raw = DB::table('app_settings')->where('key', $key)->value('value');
        if (\is_numeric($raw)) {
            return (int) $raw;
        }

        return $default;
    }

    private function resolveAppBool(string $key, bool $default): bool
    {
        if (!Schema::hasTable('app_settings')) {
            return $default;
        }

        $raw = DB::table('app_settings')->where('key', $key)->value('value');
        if ($raw === null) {
            return $default;
        }

        $parsed = filter_var($raw, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        if ($parsed === null) {
            return $default;
        }

        return $parsed;
    }
}
