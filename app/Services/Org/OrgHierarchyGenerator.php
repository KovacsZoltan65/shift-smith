<?php

declare(strict_types=1);

namespace App\Services\Org;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Org\EmployeeSupervisor;
use App\Interfaces\PositionRepositoryInterface;
use App\Services\Cache\CacheNamespaces;
use App\Services\Cache\CacheVersionService;
use App\Services\EmployeeSupervisorService;
use Carbon\CarbonImmutable;
use Faker\Factory as FakerFactory;
use Faker\Generator as FakerGenerator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

final class OrgHierarchyGenerator
{
    public function __construct(
        private readonly EmployeeSupervisorService $employeeSupervisorService,
        private readonly CacheVersionService $cacheVersionService,
        private readonly PositionOrgLevelService $positionOrgLevelService,
        private readonly PositionRepositoryInterface $positionRepository,
    ) {
    }

    /**
     * @return array{createdEmployees:int,assignedRelations:int,historyChanges:int}
     */
    public function generateForCompany(Company $company, int $tenantGroupId, int $seed): array
    {
        $faker = FakerFactory::create();
        $faker->seed($seed + (int) $company->id);
        $this->ensureRequiredMappings((int) $company->id);

        $employees = Employee::query()
            ->where('company_id', (int) $company->id)
            ->orderBy('id')
            ->get();

        $requirements = $this->structureRequirements($faker);
        $requiredTotal = array_sum($requirements);

        $createdEmployees = 0;
        if ($employees->count() < $requiredTotal) {
            $need = $requiredTotal - $employees->count();
            $createdEmployees = $this->createMissingEmployees((int) $company->id, $need, $faker);
            $employees = Employee::query()
                ->where('company_id', (int) $company->id)
                ->orderBy('id')
                ->get();
        }

        $assigned = $this->assignLevels($employees, $requirements);
        $assignedRelations = 0;
        $historyChanges = 0;

        $baseDateByEmployee = $this->buildBaseDateMap($assigned, $faker);

        EmployeeSupervisor::query()
            ->where('company_id', (int) $company->id)
            ->delete();

        $assignedRelations += $this->assignTree(
            companyId: (int) $company->id,
            assigned: $assigned,
            baseDateByEmployee: $baseDateByEmployee
        );

        $historyChanges = $this->assignHistoryChanges(
            companyId: (int) $company->id,
            assigned: $assigned,
            baseDateByEmployee: $baseDateByEmployee,
            faker: $faker
        );

        $tenantOrgNamespace = CacheNamespaces::tenantOrgHierarchy($tenantGroupId, (int) $company->id);
        $this->cacheVersionService->bump($tenantOrgNamespace.':hierarchy');

        $this->assertCompanyIntegrity((int) $company->id);

        return [
            'createdEmployees' => $createdEmployees,
            'assignedRelations' => $assignedRelations,
            'historyChanges' => $historyChanges,
        ];
    }

    private function ensureRequiredMappings(int $companyId): void
    {
        $required = [
            'CEO' => 'ceo',
            'Manager' => 'manager',
            'Osztályvezető' => 'department_head',
            'Műszakvezető' => 'shift_lead',
            'Csoportvezető' => 'team_lead',
            'Dolgozó' => 'staff',
        ];

        foreach ($required as $label => $level) {
            $this->positionOrgLevelService->upsertMapping($companyId, $label, $level, true);
        }
    }

    /**
     * @return array{
     *   ceo:list<Employee>,
     *   manager:list<Employee>,
     *   department_head:list<Employee>,
     *   shift_lead:list<Employee>,
     *   team_lead:list<Employee>,
     *   staff:list<Employee>
     * }
     */
    private function assignLevels(Collection $employees, array $requirements): array
    {
        /** @var array<string, list<Employee>> $assigned */
        $assigned = [
            'ceo' => [],
            'manager' => [],
            'department_head' => [],
            'shift_lead' => [],
            'team_lead' => [],
            'staff' => [],
        ];

        $all = $employees->values();
        $offset = 0;
        foreach (['ceo', 'manager', 'department_head', 'shift_lead', 'team_lead', 'staff'] as $level) {
            $count = (int) ($requirements[$level] ?? 0);
            $slice = $all->slice($offset, $count)->values();
            $offset += $count;

            foreach ($slice as $employee) {
                $positionLabel = $this->positionLabelForLevel($level);
                $position = $this->positionRepository->firstOrCreateInCompany(
                    companyId: (int) $employee->company_id,
                    name: $positionLabel,
                    description: $positionLabel
                );

                $employee->position_id = (int) $position->id;
                $employee->org_level = $this->positionOrgLevelService->resolveOrgLevel(
                    (int) $employee->company_id,
                    $positionLabel
                );
                $employee->save();
                $assigned[$level][] = $employee;
            }
        }

        return $assigned;
    }

    private function positionLabelForLevel(string $level): string
    {
        return match ($level) {
            'ceo' => 'CEO',
            'manager' => 'Manager',
            'department_head' => 'Osztályvezető',
            'shift_lead' => 'Műszakvezető',
            'team_lead' => 'Csoportvezető',
            default => 'Dolgozó',
        };
    }

    /**
     * @return array<string, int>
     */
    private function structureRequirements(FakerGenerator $faker): array
    {
        $ceo = 1;
        $manager = 2;
        $departmentHead = $manager * 2;
        $shiftLead = $departmentHead * 2;

        $teamLeadPerShiftLead = [];
        for ($i = 0; $i < $shiftLead; $i++) {
            $teamLeadPerShiftLead[] = $faker->numberBetween(3, 6);
        }
        $teamLead = array_sum($teamLeadPerShiftLead);

        $staff = 0;
        foreach ($teamLeadPerShiftLead as $leadsForShiftLead) {
            for ($i = 0; $i < $leadsForShiftLead; $i++) {
                $staff += $faker->numberBetween(3, 10);
            }
        }

        return [
            'ceo' => $ceo,
            'manager' => $manager,
            'department_head' => $departmentHead,
            'shift_lead' => $shiftLead,
            'team_lead' => $teamLead,
            'staff' => max(30, $staff),
        ];
    }

    private function createMissingEmployees(int $companyId, int $need, FakerGenerator $faker): int
    {
        $created = 0;
        for ($i = 0; $i < $need; $i++) {
            Employee::factory()->create([
                'company_id' => $companyId,
                'org_level' => Employee::ORG_LEVEL_STAFF,
                'hired_at' => $faker->dateTimeBetween('-3 years', '-20 days')->format('Y-m-d'),
            ]);
            $created++;
        }

        return $created;
    }

    /**
     * @param array<string, list<Employee>> $assigned
     * @return array<int, string>
     */
    private function buildBaseDateMap(array $assigned, FakerGenerator $faker): array
    {
        $map = [];
        foreach (['manager', 'department_head', 'shift_lead', 'team_lead', 'staff'] as $level) {
            foreach ($assigned[$level] as $employee) {
                $hiredAt = $employee->hired_at?->format('Y-m-d');
                $fallback = CarbonImmutable::today()->subDays(180)->format('Y-m-d');
                $map[(int) $employee->id] = is_string($hiredAt) && $hiredAt !== '' ? $hiredAt : $fallback;
            }
        }

        return $map;
    }

    /**
     * @param array<string, list<Employee>> $assigned
     * @param array<int, string> $baseDateByEmployee
     */
    private function assignTree(int $companyId, array $assigned, array $baseDateByEmployee): int
    {
        $relations = 0;
        $ceo = $assigned['ceo'][0] ?? null;
        if (! $ceo instanceof Employee) {
            throw new RuntimeException("Missing CEO for company {$companyId}");
        }

        foreach ($assigned['manager'] as $employee) {
            $this->assignWithRetry($companyId, $employee, [$ceo], $baseDateByEmployee[(int) $employee->id]);
            $relations++;
        }

        $managerBuckets = $this->splitEvenly($assigned['department_head'], $assigned['manager']);
        foreach ($managerBuckets as $managerId => $bucket) {
            $supervisor = $this->findById($assigned['manager'], $managerId);
            if (! $supervisor instanceof Employee) {
                continue;
            }
            foreach ($bucket as $employee) {
                $this->assignWithRetry($companyId, $employee, [$supervisor], $baseDateByEmployee[(int) $employee->id]);
                $relations++;
            }
        }

        $deptBuckets = $this->splitEvenly($assigned['shift_lead'], $assigned['department_head']);
        foreach ($deptBuckets as $deptHeadId => $bucket) {
            $supervisor = $this->findById($assigned['department_head'], $deptHeadId);
            if (! $supervisor instanceof Employee) {
                continue;
            }
            foreach ($bucket as $employee) {
                $this->assignWithRetry($companyId, $employee, [$supervisor], $baseDateByEmployee[(int) $employee->id]);
                $relations++;
            }
        }

        $shiftBuckets = $this->splitEvenly($assigned['team_lead'], $assigned['shift_lead']);
        foreach ($shiftBuckets as $shiftLeadId => $bucket) {
            $supervisor = $this->findById($assigned['shift_lead'], $shiftLeadId);
            if (! $supervisor instanceof Employee) {
                continue;
            }
            foreach ($bucket as $employee) {
                $this->assignWithRetry($companyId, $employee, [$supervisor], $baseDateByEmployee[(int) $employee->id]);
                $relations++;
            }
        }

        $teamBuckets = $this->splitEvenly($assigned['staff'], $assigned['team_lead']);
        foreach ($teamBuckets as $teamLeadId => $bucket) {
            $supervisor = $this->findById($assigned['team_lead'], $teamLeadId);
            if (! $supervisor instanceof Employee) {
                continue;
            }
            foreach ($bucket as $employee) {
                $this->assignWithRetry($companyId, $employee, [$supervisor], $baseDateByEmployee[(int) $employee->id]);
                $relations++;
            }
        }

        return $relations;
    }

    /**
     * @param array<string, list<Employee>> $assigned
     * @param array<int, string> $baseDateByEmployee
     */
    private function assignHistoryChanges(
        int $companyId,
        array $assigned,
        array $baseDateByEmployee,
        FakerGenerator $faker
    ): int {
        $teamLeads = $assigned['team_lead'];
        $staffList = $assigned['staff'];
        if (count($teamLeads) < 2 || $staffList === []) {
            return 0;
        }

        $targetCount = max(1, (int) floor(count($staffList) * 0.15));
        $selected = collect($staffList)->shuffle()->take($targetCount)->values();
        $changes = 0;

        foreach ($selected as $staff) {
            $active = EmployeeSupervisor::query()
                ->where('company_id', $companyId)
                ->where('employee_id', (int) $staff->id)
                ->whereNull('valid_to')
                ->first();

            if (! $active instanceof EmployeeSupervisor) {
                continue;
            }

            $currentSupervisorId = (int) $active->supervisor_employee_id;
            $candidates = array_values(array_filter(
                $teamLeads,
                static fn (Employee $lead): bool => (int) $lead->id !== $currentSupervisorId
            ));

            if ($candidates === []) {
                continue;
            }

            $baseFrom = CarbonImmutable::parse($baseDateByEmployee[(int) $staff->id] ?? CarbonImmutable::today()->subDays(180)->format('Y-m-d'));
            $changeDate = $baseFrom->addDays($faker->numberBetween(30, 120));
            if ($changeDate->isFuture()) {
                $changeDate = CarbonImmutable::today()->subDay();
            }
            if ($changeDate->lte($baseFrom)) {
                $changeDate = $baseFrom->addDay();
            }

            $this->assignWithRetry(
                companyId: $companyId,
                employee: $staff,
                supervisorCandidates: $candidates,
                validFrom: $changeDate->format('Y-m-d')
            );
            $changes++;
        }

        return $changes;
    }

    /**
     * @param list<Employee> $supervisorCandidates
     */
    private function assignWithRetry(
        int $companyId,
        Employee $employee,
        array $supervisorCandidates,
        string $validFrom
    ): void {
        $attempts = 0;
        $lastError = null;
        $candidates = collect($supervisorCandidates)->shuffle()->values();

        while ($attempts < 10) {
            $attempts++;
            $candidate = $candidates->get(($attempts - 1) % max(1, $candidates->count()));
            if (! $candidate instanceof Employee) {
                continue;
            }

            try {
                $this->employeeSupervisorService->assignSupervisor(
                    companyId: $companyId,
                    employeeId: (int) $employee->id,
                    supervisorEmployeeId: (int) $candidate->id,
                    validFrom: $validFrom,
                    actorUserId: null
                );
                return;
            } catch (Throwable $exception) {
                $lastError = $exception;
                Log::warning('org_hierarchy.seeder_assign_failed', [
                    'company_id' => $companyId,
                    'employee_id' => (int) $employee->id,
                    'supervisor_id' => (int) $candidate->id,
                    'valid_from' => $validFrom,
                    'error' => $exception->getMessage(),
                    'attempt' => $attempts,
                ]);
            }
        }

        throw new RuntimeException(
            sprintf(
                'Unable to assign supervisor after %d attempts (company=%d, employee=%d, valid_from=%s). Last error: %s',
                10,
                $companyId,
                (int) $employee->id,
                $validFrom,
                $lastError?->getMessage() ?? 'unknown'
            )
        );
    }

    /**
     * @param list<Employee> $items
     * @param list<Employee> $supervisors
     * @return array<int, list<Employee>>
     */
    private function splitEvenly(array $items, array $supervisors): array
    {
        $result = [];
        foreach ($supervisors as $supervisor) {
            $result[(int) $supervisor->id] = [];
        }

        if ($supervisors === []) {
            return $result;
        }

        $index = 0;
        $count = count($supervisors);
        foreach ($items as $item) {
            $supervisor = $supervisors[$index % $count];
            $result[(int) $supervisor->id][] = $item;
            $index++;
        }

        return $result;
    }

    /**
     * @param list<Employee> $list
     */
    private function findById(array $list, int $id): ?Employee
    {
        foreach ($list as $item) {
            if ((int) $item->id === $id) {
                return $item;
            }
        }

        return null;
    }

    private function assertCompanyIntegrity(int $companyId): void
    {
        $ceoIds = Employee::query()
            ->where('company_id', $companyId)
            ->where('org_level', Employee::ORG_LEVEL_CEO)
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->values()
            ->all();

        if ($ceoIds === []) {
            throw new RuntimeException("Integrity fail: company {$companyId} has no CEO.");
        }

        $ceoWithSupervisor = EmployeeSupervisor::query()
            ->where('company_id', $companyId)
            ->whereIn('employee_id', $ceoIds)
            ->whereNull('valid_to')
            ->count();

        if ($ceoWithSupervisor > 0) {
            throw new RuntimeException("Integrity fail: company {$companyId} CEO has active supervisor.");
        }

        $nonCeoIds = Employee::query()
            ->where('company_id', $companyId)
            ->where('org_level', '!=', Employee::ORG_LEVEL_CEO)
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->values()
            ->all();

        if ($nonCeoIds !== []) {
            $activeCounts = EmployeeSupervisor::query()
                ->where('company_id', $companyId)
                ->whereIn('employee_id', $nonCeoIds)
                ->whereNull('valid_to')
                ->selectRaw('employee_id, COUNT(*) as aggregate')
                ->groupBy('employee_id')
                ->pluck('aggregate', 'employee_id');

            foreach ($nonCeoIds as $employeeId) {
                $count = (int) ($activeCounts[$employeeId] ?? 0);
                if ($count !== 1) {
                    throw new RuntimeException("Integrity fail: employee {$employeeId} in company {$companyId} has {$count} active supervisors.");
                }
            }
        }

        $this->assertNoOverlaps($companyId);
        $this->assertNoCycles($companyId);
    }

    private function assertNoOverlaps(int $companyId): void
    {
        $rows = EmployeeSupervisor::query()
            ->where('company_id', $companyId)
            ->orderBy('employee_id')
            ->orderBy('valid_from')
            ->get();

        $byEmployee = $rows->groupBy('employee_id');
        foreach ($byEmployee as $employeeId => $intervals) {
            $previousTo = null;
            foreach ($intervals as $interval) {
                $from = CarbonImmutable::parse((string) $interval->valid_from);
                $to = $interval->valid_to !== null
                    ? CarbonImmutable::parse((string) $interval->valid_to)
                    : CarbonImmutable::create(9999, 12, 31);

                if ($previousTo !== null && $from->lte($previousTo)) {
                    throw new RuntimeException("Integrity fail: overlap detected for employee {$employeeId} in company {$companyId}.");
                }

                $previousTo = $to;
            }
        }
    }

    private function assertNoCycles(int $companyId): void
    {
        $edges = EmployeeSupervisor::query()
            ->where('company_id', $companyId)
            ->whereNull('valid_to')
            ->get(['employee_id', 'supervisor_employee_id']);

        $graph = [];
        foreach ($edges as $edge) {
            $employeeId = (int) $edge->employee_id;
            $supervisorId = (int) $edge->supervisor_employee_id;
            $graph[$supervisorId][] = $employeeId;
            $graph[$employeeId] = $graph[$employeeId] ?? [];
        }

        $visited = [];
        $inStack = [];

        $dfs = function (int $node) use (&$dfs, &$graph, &$visited, &$inStack, $companyId): void {
            if (($inStack[$node] ?? false) === true) {
                throw new RuntimeException("Integrity fail: cycle detected in company {$companyId}.");
            }

            if (($visited[$node] ?? false) === true) {
                return;
            }

            $visited[$node] = true;
            $inStack[$node] = true;

            foreach ($graph[$node] ?? [] as $next) {
                $dfs((int) $next);
            }

            $inStack[$node] = false;
        };

        foreach (array_keys($graph) as $node) {
            $dfs((int) $node);
        }
    }
}
