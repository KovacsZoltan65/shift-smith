<?php

declare(strict_types=1);

namespace App\Services\Scheduling;

use App\Data\Scheduling\AutoPlan\GenerateInputData;
use App\Data\Scheduling\AutoPlan\GenerateResultData;
use App\Models\WorkShift;
use App\Repositories\Scheduling\AutoPlanRepository;
use App\Services\Cache\CacheNamespaces;
use App\Services\Cache\CacheVersionService;
use App\Services\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AutoPlanService
{
    public function __construct(
        private readonly AutoPlanRepository $repository,
        private readonly CacheVersionService $cacheVersionService,
        private readonly TenantContext $tenantContext
    ) {}

    public function generate(int $companyId, int $userId, GenerateInputData $input): GenerateResultData
    {
        $monthStart = CarbonImmutable::createFromFormat('Y-m', $input->month)->startOfMonth();
        $monthEnd = $monthStart->endOfMonth();

        $weekdayDemand = $this->normalizeDemand($input->weekday_demand);
        $weekendDemand = $this->normalizeDemand($input->weekend_demand);
        $shiftIds = array_values(array_unique(array_merge(array_keys($weekdayDemand), array_keys($weekendDemand))));

        $employees = $this->repository->employeesByCompany($companyId, $input->employee_ids);
        if ($employees->count() !== count($input->employee_ids)) {
            throw ValidationException::withMessages([
                'employee_ids' => 'A kiválasztott dolgozók között cégidegen elem található.',
            ]);
        }

        $shifts = $this->repository->shiftsByCompany($companyId, $shiftIds)->keyBy('id');
        if ($shifts->count() !== count($shiftIds)) {
            throw ValidationException::withMessages([
                'demand' => 'A demand műszakok között cégidegen elem található.',
            ]);
        }

        $resolvedRules = $this->resolveRules($input);
        $lookaroundDays = max(7, $resolvedRules['max_consecutive_days'] + 2);

        $existing = $this->repository->assignmentsForEmployeesBetween(
            companyId: $companyId,
            employeeIds: array_values($employees->pluck('id')->map(fn ($id): int => (int) $id)->all()),
            dateFrom: $monthStart->subDays($lookaroundDays)->toDateString(),
            dateTo: $monthEnd->addDays($lookaroundDays)->toDateString(),
        );

        $state = $this->buildState($employees, $existing, $shifts, $monthStart, $monthEnd);
        $slots = $this->buildSlots($monthStart, $monthEnd, $weekdayDemand, $weekendDemand);

        $pendingRows = [];
        $missing = [];
        $filled = 0;
        $now = now()->toDateTimeString();

        foreach ($slots as $slot) {
            $shift = $shifts->get($slot['shift_id']);
            if (!$shift instanceof WorkShift) {
                $missing[] = [
                    'date' => $slot['date'],
                    'shift_id' => (int) $slot['shift_id'],
                    'reason' => 'A műszak nem található.',
                ];
                continue;
            }

            $selection = $this->selectCandidateEmployee(
                employeeIds: array_values($state['employee_ids']),
                state: $state,
                slotDate: $slot['date'],
                shift: $shift,
                minRestHours: $resolvedRules['min_rest_hours'],
                maxConsecutiveDays: $resolvedRules['max_consecutive_days'],
                weekendFairness: $resolvedRules['weekend_fairness']
            );

            if ($selection['employee_id'] === null) {
                $missing[] = [
                    'date' => $slot['date'],
                    'shift_id' => (int) $slot['shift_id'],
                    'reason' => $selection['reason'],
                ];
                continue;
            }

            $candidateEmployeeId = (int) $selection['employee_id'];

            $pendingRows[] = [
                'company_id' => $companyId,
                'work_schedule_id' => 0,
                'employee_id' => $candidateEmployeeId,
                'work_shift_id' => (int) $shift->id,
                'date' => $slot['date'],
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $this->applyPlannedAssignment(
                state: $state,
                employeeId: $candidateEmployeeId,
                slotDate: $slot['date'],
                shift: $shift,
                monthStart: $monthStart,
                monthEnd: $monthEnd
            );

            $filled++;
        }

        $slotsTotal = count($slots);
        $coverageRate = $slotsTotal > 0
            ? round(($filled / $slotsTotal) * 100, 2)
            : 0.0;

        $result = DB::transaction(function () use (
            $companyId,
            $userId,
            $input,
            $monthStart,
            $monthEnd,
            $resolvedRules,
            $pendingRows,
            $missing,
            $slotsTotal,
            $filled,
            $coverageRate
        ): GenerateResultData {
            $schedule = $this->repository->createDraftSchedule(
                companyId: $companyId,
                month: $input->month,
                dateFrom: $monthStart->toDateString(),
                dateTo: $monthEnd->toDateString(),
            );

            $insertRows = array_map(
                static function (array $row) use ($schedule): array {
                    $row['work_schedule_id'] = (int) $schedule->id;
                    return $row;
                },
                $pendingRows
            );

            $createdCount = $this->repository->insertAssignments($insertRows);

            $resultJson = [
                'rules' => $resolvedRules,
                'coverage' => [
                    'slots_total' => $slotsTotal,
                    'slots_filled' => $filled,
                    'slots_missing' => $slotsTotal - $filled,
                    'coverage_rate' => $coverageRate,
                ],
                'missing' => $missing,
                'assignments_created' => $createdCount,
            ];

            $report = $this->repository->createGenerationReport(
                companyId: $companyId,
                workScheduleId: (int) $schedule->id,
                inputJson: [
                    'month' => $input->month,
                    'employee_ids' => $input->employee_ids,
                    'demand' => [
                        'weekday' => $input->weekday_demand,
                        'weekend' => $input->weekend_demand,
                    ],
                    'rules' => $input->rules,
                    'rules_resolved' => $resolvedRules,
                ],
                resultJson: $resultJson,
                createdBy: $userId
            );

            $this->repository->persistAutoPlanRules($resolvedRules);

            DB::afterCommit(function () use ($companyId, $userId, $schedule, $report, $resultJson): void {
                $tenantGroupId = $this->tenantContext->currentTenantGroupIdOrFail();
                $workSchedulesNamespace = CacheNamespaces::tenantWorkSchedules($tenantGroupId);
                $workScheduleAssignmentsNamespace = CacheNamespaces::tenantWorkScheduleAssignments($tenantGroupId);

                $this->cacheVersionService->bump($workSchedulesNamespace);
                $this->cacheVersionService->bump($workScheduleAssignmentsNamespace);

                activity('work_schedules')
                    ->performedOn($schedule)
                    ->event('autoplan.generate')
                    ->withProperties([
                        'company_id' => $companyId,
                        'created_by' => $userId,
                        'work_schedule_id' => (int) $schedule->id,
                        'generation_report_id' => (int) $report->id,
                        'coverage' => $resultJson['coverage'],
                    ])
                    ->log('AutoPlan draft generated.');
            });

            return new GenerateResultData(
                work_schedule: [
                    'id' => (int) $schedule->id,
                    'name' => (string) $schedule->name,
                    'status' => (string) $schedule->status,
                    'date_from' => (string) $schedule->date_from->format('Y-m-d'),
                    'date_to' => (string) $schedule->date_to->format('Y-m-d'),
                ],
                assignments_created: $createdCount,
                rules: $resolvedRules,
                coverage: $resultJson['coverage'],
                missing: $missing,
                generation_report_id: (int) $report->id,
            );
        });

        return $result;
    }

    /**
     * @return array{
     *   min_rest_hours:int,
     *   max_consecutive_days:int,
     *   weekend_fairness:bool
     * }
     */
    public function defaults(): array
    {
        return $this->repository->getAutoPlanRules();
    }

    /**
     * @param list<\App\Data\Scheduling\AutoPlan\DemandItemData> $items
     * @return array<int,int>
     */
    private function normalizeDemand(array $items): array
    {
        $out = [];
        foreach ($items as $item) {
            $shiftId = (int) $item->shift_id;
            $out[$shiftId] = (int) ($out[$shiftId] ?? 0) + (int) $item->required_count;
        }

        return $out;
    }

    /**
     * @return array{
     *   min_rest_hours:int,
     *   max_consecutive_days:int,
     *   weekend_fairness:bool
     * }
     */
    private function resolveRules(GenerateInputData $input): array
    {
        $defaults = $this->repository->getAutoPlanRules();

        $minRest = $input->rules?->min_rest_hours ?? $defaults['min_rest_hours'];
        $maxConsecutive = $input->rules?->max_consecutive_days ?? $defaults['max_consecutive_days'];
        $weekendFairness = $input->rules?->weekend_fairness ?? $defaults['weekend_fairness'];

        return [
            'min_rest_hours' => max(0, (int) $minRest),
            'max_consecutive_days' => max(1, (int) $maxConsecutive),
            'weekend_fairness' => (bool) $weekendFairness,
        ];
    }

    /**
     * @param Collection<int,\App\Models\Employee> $employees
     * @param Collection<int,\App\Models\WorkShiftAssignment> $existing
     * @param Collection<int,\App\Models\WorkShift> $shifts
     * @return array{
     *   employee_ids:list<int>,
     *   employees:array<int,array{
     *     occupied_dates:array<string,bool>,
     *     consecutive_dates:array<string,bool>,
     *     intervals:list<array{start_at:CarbonImmutable,end_at:CarbonImmutable,date:string}>,
     *     month_load:int,
     *     month_weekend_load:int
     *   }>
     * }
     */
    private function buildState(
        Collection $employees,
        Collection $existing,
        Collection $shifts,
        CarbonImmutable $monthStart,
        CarbonImmutable $monthEnd
    ): array {
        $state = [
            'employee_ids' => array_values($employees->pluck('id')->map(fn ($id): int => (int) $id)->all()),
            'employees' => [],
        ];

        foreach ($state['employee_ids'] as $employeeId) {
            $state['employees'][$employeeId] = [
                'occupied_dates' => [],
                'consecutive_dates' => [],
                'intervals' => [],
                'month_load' => 0,
                'month_weekend_load' => 0,
            ];
        }

        foreach ($existing as $assignment) {
            $employeeId = (int) $assignment->employee_id;
            if (!isset($state['employees'][$employeeId])) {
                continue;
            }

            $date = (string) $assignment->date->format('Y-m-d');
            // Foglaltság (egy dolgozó/nap csak egy assignment lehet)
            $state['employees'][$employeeId]['occupied_dates'][$date] = true;
            // Egymást követő napok számításához csak múltbeli kontextust veszünk át.
            if (CarbonImmutable::parse($date)->lessThan($monthStart)) {
                $state['employees'][$employeeId]['consecutive_dates'][$date] = true;
            }

            $shift = $shifts->get((int) $assignment->work_shift_id) ?? $assignment->workShift;
            if ($shift instanceof WorkShift) {
                $interval = $this->toInterval($date, (string) $shift->start_time, (string) $shift->end_time);
                if ($interval !== null) {
                    $state['employees'][$employeeId]['intervals'][] = $interval;
                }
            }

            $day = CarbonImmutable::parse($date);
            if ($day->betweenIncluded($monthStart, $monthEnd)) {
                $state['employees'][$employeeId]['month_load']++;
                if ($day->isWeekend()) {
                    $state['employees'][$employeeId]['month_weekend_load']++;
                }
            }
        }

        foreach ($state['employee_ids'] as $employeeId) {
            usort(
                $state['employees'][$employeeId]['intervals'],
                static fn (array $a, array $b): int => $a['start_at']->lessThan($b['start_at']) ? -1 : 1
            );
        }

        return $state;
    }

    /**
     * @param array<int,int> $weekdayDemand
     * @param array<int,int> $weekendDemand
     * @return list<array{date:string,shift_id:int}>
     */
    private function buildSlots(
        CarbonImmutable $monthStart,
        CarbonImmutable $monthEnd,
        array $weekdayDemand,
        array $weekendDemand
    ): array {
        $slots = [];
        $cursor = $monthStart;

        while ($cursor->lessThanOrEqualTo($monthEnd)) {
            $demand = $cursor->isWeekend() ? $weekendDemand : $weekdayDemand;
            foreach ($demand as $shiftId => $requiredCount) {
                for ($i = 0; $i < $requiredCount; $i++) {
                    $slots[] = [
                        'date' => $cursor->toDateString(),
                        'shift_id' => (int) $shiftId,
                    ];
                }
            }

            $cursor = $cursor->addDay();
        }

        return $slots;
    }

    /**
     * @param list<int> $employeeIds
     * @param array<string,mixed> $state
     */
    private function selectCandidateEmployee(
        array $employeeIds,
        array $state,
        string $slotDate,
        WorkShift $shift,
        int $minRestHours,
        int $maxConsecutiveDays,
        bool $weekendFairness
    ): array {
        $candidates = [];
        $rejectionStats = [];
        $slotDay = CarbonImmutable::parse($slotDate);
        $isWeekend = $slotDay->isWeekend();

        foreach ($employeeIds as $employeeId) {
            $rejectionReason = $this->candidateRejectionReason(
                state: $state,
                employeeId: $employeeId,
                slotDate: $slotDate,
                shift: $shift,
                minRestHours: $minRestHours,
                maxConsecutiveDays: $maxConsecutiveDays
            );

            if ($rejectionReason !== null) {
                $rejectionStats[$rejectionReason] = (int) ($rejectionStats[$rejectionReason] ?? 0) + 1;
                continue;
            }

            $load = (int) $state['employees'][$employeeId]['month_load'];
            $weekendLoad = (int) $state['employees'][$employeeId]['month_weekend_load'];
            $score = $load * 100;
            if ($weekendFairness && $isWeekend) {
                $score += $weekendLoad * 35;
            }

            $candidates[] = [
                'employee_id' => $employeeId,
                'score' => $score,
            ];
        }

        if ($candidates === []) {
            arsort($rejectionStats);
            $dominantReason = (string) array_key_first($rejectionStats);

            return [
                'employee_id' => null,
                'reason' => $this->mapRejectionReason($dominantReason),
            ];
        }

        usort($candidates, static function (array $a, array $b): int {
            return $a['score'] <=> $b['score'] ?: ($a['employee_id'] <=> $b['employee_id']);
        });

        return [
            'employee_id' => (int) $candidates[0]['employee_id'],
            'reason' => '',
        ];
    }

    /**
     * @param array<string,mixed> $state
     */
    private function candidateRejectionReason(
        array $state,
        int $employeeId,
        string $slotDate,
        WorkShift $shift,
        int $minRestHours,
        int $maxConsecutiveDays
    ): ?string {
        if (isset($state['employees'][$employeeId]['occupied_dates'][$slotDate])) {
            return 'already_occupied';
        }

        if (!$this->respectsConsecutiveRule($state, $employeeId, $slotDate, $maxConsecutiveDays)) {
            return 'max_consecutive_days';
        }

        if (!$this->respectsMinRestRule($state, $employeeId, $slotDate, $shift, $minRestHours)) {
            return 'min_rest_hours';
        }

        return null;
    }

    private function mapRejectionReason(string $reasonCode): string
    {
        return match ($reasonCode) {
            'already_occupied' => 'A kiválasztott dolgozóknak erre a napra már van beosztása.',
            'max_consecutive_days' => 'A max. egymást követő munkanap szabály miatt nincs kiosztható jelölt.',
            'min_rest_hours' => 'A minimum pihenőidő szabály miatt nincs kiosztható jelölt.',
            default => 'Nincs szabály-kompatibilis jelölt.',
        };
    }

    /**
     * @param array<string,mixed> $state
     */
    private function respectsConsecutiveRule(
        array $state,
        int $employeeId,
        string $slotDate,
        int $maxConsecutiveDays
    ): bool {
        $dates = $state['employees'][$employeeId]['consecutive_dates'];
        $day = CarbonImmutable::parse($slotDate);

        $runBefore = 0;
        $cursor = $day->subDay();
        while (isset($dates[$cursor->toDateString()])) {
            $runBefore++;
            $cursor = $cursor->subDay();
        }

        $runAfter = 0;
        $cursor = $day->addDay();
        while (isset($dates[$cursor->toDateString()])) {
            $runAfter++;
            $cursor = $cursor->addDay();
        }

        return ($runBefore + 1 + $runAfter) <= $maxConsecutiveDays;
    }

    /**
     * @param array<string,mixed> $state
     */
    private function respectsMinRestRule(
        array $state,
        int $employeeId,
        string $slotDate,
        WorkShift $shift,
        int $minRestHours
    ): bool {
        if ($minRestHours <= 0) {
            return true;
        }

        $candidate = $this->toInterval($slotDate, (string) $shift->start_time, (string) $shift->end_time);
        if ($candidate === null) {
            return true;
        }

        $previousEnd = null;
        foreach ($state['employees'][$employeeId]['intervals'] as $interval) {
            if ($interval['end_at']->lessThanOrEqualTo($candidate['start_at'])) {
                $previousEnd = $interval['end_at'];
                continue;
            }

            break;
        }

        if (!$previousEnd instanceof CarbonImmutable) {
            return true;
        }

        $restMinutes = $previousEnd->diffInMinutes($candidate['start_at'], false);

        return $restMinutes >= ($minRestHours * 60);
    }

    /**
     * @param array<string,mixed> $state
     */
    private function applyPlannedAssignment(
        array &$state,
        int $employeeId,
        string $slotDate,
        WorkShift $shift,
        CarbonImmutable $monthStart,
        CarbonImmutable $monthEnd
    ): void {
        $state['employees'][$employeeId]['occupied_dates'][$slotDate] = true;
        $state['employees'][$employeeId]['consecutive_dates'][$slotDate] = true;

        $interval = $this->toInterval($slotDate, (string) $shift->start_time, (string) $shift->end_time);
        if ($interval !== null) {
            $state['employees'][$employeeId]['intervals'][] = $interval;
            usort(
                $state['employees'][$employeeId]['intervals'],
                static fn (array $a, array $b): int => $a['start_at']->lessThan($b['start_at']) ? -1 : 1
            );
        }

        $day = CarbonImmutable::parse($slotDate);
        if ($day->betweenIncluded($monthStart, $monthEnd)) {
            $state['employees'][$employeeId]['month_load']++;
            if ($day->isWeekend()) {
                $state['employees'][$employeeId]['month_weekend_load']++;
            }
        }
    }

    /**
     * @return array{start_at:CarbonImmutable,end_at:CarbonImmutable,date:string}|null
     */
    private function toInterval(string $date, string $startTime, string $endTime): ?array
    {
        if ($startTime === '' || $endTime === '') {
            return null;
        }

        $startAt = CarbonImmutable::parse(sprintf('%s %s', $date, substr($startTime, 0, 8)));
        $endAt = CarbonImmutable::parse(sprintf('%s %s', $date, substr($endTime, 0, 8)));
        if ($endAt->lessThanOrEqualTo($startAt)) {
            $endAt = $endAt->addDay();
        }

        return [
            'date' => $date,
            'start_at' => $startAt,
            'end_at' => $endAt,
        ];
    }
}
