<?php

declare(strict_types=1);

namespace App\Services\Org;

use App\Models\Employee;
use App\Repositories\EmployeeSupervisorRepositoryInterface;
use App\Repositories\Org\OrgHierarchyRepositoryInterface;
use App\Services\EmployeeSupervisorService;
use App\Services\HierarchyIntegrityService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class OrgHierarchyMutationService
{
    public const MODE_EMPLOYEE_ONLY = 'employee_only';
    public const MODE_LEADER_WITH_SUBORDINATES = 'leader_with_subordinates';
    public const MODE_LEADER_WITHOUT_SUBORDINATES = 'leader_without_subordinates';
    public const MODE_MOVE_SUBORDINATES_ONLY = 'move_subordinates_only';

    public const STRATEGY_REASSIGN_OLD = 'reassign_to_old_supervisor';
    public const STRATEGY_REASSIGN_SPECIFIC = 'reassign_to_specific_supervisor';
    public const STRATEGY_KEEP_WITH_LEADER = 'keep_with_leader';

    public function __construct(
        private readonly EmployeeSupervisorRepositoryInterface $employeeSupervisorRepository,
        private readonly OrgHierarchyRepositoryInterface $orgHierarchyRepository,
        private readonly HierarchyIntegrityService $hierarchyIntegrityService,
        private readonly EmployeeSupervisorService $employeeSupervisorService,
    ) {
    }

    /**
     * @param array{
     *   company_id:int,
     *   employee_id:int,
     *   new_supervisor_employee_id:int|null,
     *   mode:string,
     *   subordinates_strategy:string,
     *   target_supervisor_for_subordinates:int|null,
     *   effective_from:string,
     *   at_date:string
     * } $payload
     * @return array{
     *   meta: array<string, mixed>,
     *   affected_employee_ids: list<int>,
     *   affected_count:int,
     *   warnings:list<string>,
     *   errors:list<string>
     * }
     */
    public function previewMove(array $payload): array
    {
        $companyId = (int) $payload['company_id'];
        $effectiveFrom = CarbonImmutable::parse($payload['effective_from'])->startOfDay();
        $atDate = CarbonImmutable::parse($payload['at_date'])->startOfDay();

        $warnings = [];
        $errors = [];
        $plan = [];

        try {
            $plan = $this->buildPlan($payload, $atDate, $warnings);
            foreach ($plan as $item) {
                $this->hierarchyIntegrityService->validateNewSupervisorRelationOrFail(
                    companyId: $companyId,
                    employeeId: $item['employee_id'],
                    supervisorEmployeeId: $item['supervisor_employee_id'],
                    validFrom: $effectiveFrom,
                    enforceOverlap: false,
                );
            }
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $messages) {
                foreach ($messages as $message) {
                    $errors[] = (string) $message;
                }
            }
        }

        $affectedIds = array_values(array_unique(array_map(
            static fn (array $item): int => (int) $item['employee_id'],
            $plan
        )));

        return [
            'meta' => [
                'company_id' => $companyId,
                'employee_id' => (int) $payload['employee_id'],
                'new_supervisor_employee_id' => $payload['new_supervisor_employee_id'],
                'mode' => (string) $payload['mode'],
                'effective_from' => $effectiveFrom->toDateString(),
                'at_date' => $atDate->toDateString(),
            ],
            'affected_employee_ids' => $affectedIds,
            'affected_count' => count($affectedIds),
            'warnings' => array_values(array_unique($warnings)),
            'errors' => array_values(array_unique($errors)),
        ];
    }

    /**
     * @param array{
     *   company_id:int,
     *   employee_id:int,
     *   new_supervisor_employee_id:int|null,
     *   mode:string,
     *   subordinates_strategy:string,
     *   target_supervisor_for_subordinates:int|null,
     *   effective_from:string,
     *   at_date:string
     * } $payload
     * @return array{
     *   success:bool,
     *   affected_count:int,
     *   affected_employee_ids:list<int>,
     *   new_root_id:int|null
     * }
     */
    public function move(array $payload, ?int $actorUserId = null): array
    {
        $preview = $this->previewMove($payload);
        if ($preview['errors'] !== []) {
            throw ValidationException::withMessages([
                'move' => $preview['errors'],
            ]);
        }

        $effectiveFrom = CarbonImmutable::parse($payload['effective_from'])->toDateString();
        $atDate = CarbonImmutable::parse($payload['at_date'])->startOfDay();
        $warnings = [];

        $plan = $this->buildPlan($payload, $atDate, $warnings);

        DB::transaction(function () use ($plan, $payload, $effectiveFrom, $actorUserId): void {
            foreach ($plan as $item) {
                $active = $this->employeeSupervisorRepository->findActiveSupervisor(
                    (int) $payload['company_id'],
                    (int) $item['employee_id'],
                    CarbonImmutable::parse($effectiveFrom)
                );

                if ($active !== null && (int) $active->supervisor_employee_id === (int) $item['supervisor_employee_id']) {
                    continue;
                }

                $this->employeeSupervisorService->assignSupervisor(
                    companyId: (int) $payload['company_id'],
                    employeeId: (int) $item['employee_id'],
                    supervisorEmployeeId: (int) $item['supervisor_employee_id'],
                    validFrom: $effectiveFrom,
                    actorUserId: $actorUserId,
                );
            }
        });

        return [
            'success' => true,
            'affected_count' => $preview['affected_count'],
            'affected_employee_ids' => $preview['affected_employee_ids'],
            'new_root_id' => (int) $payload['employee_id'],
        ];
    }

    /**
     * @return array{
     *   meta: array<string, mixed>,
     *   ok: bool,
     *   issues: array{
     *     cycles:list<array<int>>,
     *     overlaps:list<array{employee_id:int,first_id:int,second_id:int}>,
     *     missing_supervisor:list<int>,
     *     multiple_active:list<array{employee_id:int,count:int}>,
     *     ceo_has_supervisor:list<int>
     *   }
     * }
     */
    public function companyIntegrityReport(int $companyId, CarbonImmutable $atDate): array
    {
        $employees = $this->orgHierarchyRepository->listEmployeesInCompany($companyId);
        $employeeIds = $employees->pluck('id')->map(static fn ($id): int => (int) $id)->all();
        $ceoIds = $employees
            ->filter(static fn (Employee $employee): bool => $employee->org_level === Employee::ORG_LEVEL_CEO)
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
        $nonCeoIds = $employees
            ->filter(static fn (Employee $employee): bool => $employee->org_level !== Employee::ORG_LEVEL_CEO)
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        $activeGrouped = $this->employeeSupervisorRepository->listActiveSupervisorIdsGroupedByEmployee($companyId, $atDate);
        $activeEdges = $this->employeeSupervisorRepository->listActiveRelations($companyId, $atDate);

        $multipleActive = [];
        foreach ($activeGrouped as $employeeId => $supervisorIds) {
            $count = count(array_unique($supervisorIds));
            if ($count > 1) {
                $multipleActive[] = [
                    'employee_id' => (int) $employeeId,
                    'count' => $count,
                ];
            }
        }

        $missingSupervisor = array_values(array_filter(
            $nonCeoIds,
            static fn (int $employeeId): bool => ! array_key_exists($employeeId, $activeGrouped) || $activeGrouped[$employeeId] === []
        ));

        $ceoHasSupervisor = array_values(array_filter(
            $ceoIds,
            static fn (int $employeeId): bool => array_key_exists($employeeId, $activeGrouped) && $activeGrouped[$employeeId] !== []
        ));

        $overlaps = $this->detectOverlaps($companyId);
        $cycles = $this->detectCycles($employeeIds, $activeEdges);

        $issues = [
            'cycles' => $cycles,
            'overlaps' => $overlaps,
            'missing_supervisor' => $missingSupervisor,
            'multiple_active' => $multipleActive,
            'ceo_has_supervisor' => $ceoHasSupervisor,
        ];

        $ok = $issues['cycles'] === []
            && $issues['overlaps'] === []
            && $issues['missing_supervisor'] === []
            && $issues['multiple_active'] === []
            && $issues['ceo_has_supervisor'] === [];

        return [
            'meta' => [
                'company_id' => $companyId,
                'at_date' => $atDate->toDateString(),
            ],
            'ok' => $ok,
            'issues' => $issues,
        ];
    }

    /**
     * @param array{
     *   company_id:int,
     *   employee_id:int,
     *   new_supervisor_employee_id:int|null,
     *   mode:string,
     *   subordinates_strategy:string,
     *   target_supervisor_for_subordinates:int|null,
     *   effective_from:string,
     *   at_date:string
     * } $payload
     * @param list<string> $warnings
     * @return list<array{employee_id:int,supervisor_employee_id:int}>
     */
    private function buildPlan(array $payload, CarbonImmutable $atDate, array &$warnings): array
    {
        $companyId = (int) $payload['company_id'];
        $employeeId = (int) $payload['employee_id'];
        $newSupervisorId = $payload['new_supervisor_employee_id'] !== null ? (int) $payload['new_supervisor_employee_id'] : null;
        $mode = (string) $payload['mode'];
        $strategy = (string) $payload['subordinates_strategy'];
        $targetForSubordinates = $payload['target_supervisor_for_subordinates'] !== null
            ? (int) $payload['target_supervisor_for_subordinates']
            : null;

        $plan = [];
        $directSubordinates = $this->employeeSupervisorRepository->listDirectSubordinates($companyId, $employeeId, $atDate);
        $activeSupervisor = $this->employeeSupervisorRepository->findActiveSupervisor($companyId, $employeeId, $atDate);
        $oldSupervisorId = $activeSupervisor !== null ? (int) $activeSupervisor->supervisor_employee_id : null;

        if ($mode === self::MODE_EMPLOYEE_ONLY || $mode === self::MODE_LEADER_WITH_SUBORDINATES) {
            if ($newSupervisorId === null) {
                throw ValidationException::withMessages([
                    'new_supervisor_employee_id' => 'Az új felettes megadása kötelező.',
                ]);
            }

            $plan[] = ['employee_id' => $employeeId, 'supervisor_employee_id' => $newSupervisorId];
            return $plan;
        }

        if ($mode === self::MODE_LEADER_WITHOUT_SUBORDINATES) {
            if ($newSupervisorId === null) {
                throw ValidationException::withMessages([
                    'new_supervisor_employee_id' => 'Az új felettes megadása kötelező.',
                ]);
            }

            $plan[] = ['employee_id' => $employeeId, 'supervisor_employee_id' => $newSupervisorId];

            if ($strategy === self::STRATEGY_KEEP_WITH_LEADER) {
                return $plan;
            }

            $targetSupervisorId = null;
            if ($strategy === self::STRATEGY_REASSIGN_OLD) {
                $targetSupervisorId = $oldSupervisorId;
                if ($targetSupervisorId === null) {
                    throw ValidationException::withMessages([
                        'subordinates_strategy' => 'A vezetőnek nincs korábbi felettese, ezért nem lehet oda visszarendezni a beosztottakat.',
                    ]);
                }
            }

            if ($strategy === self::STRATEGY_REASSIGN_SPECIFIC) {
                if ($targetForSubordinates === null) {
                    throw ValidationException::withMessages([
                        'target_supervisor_for_subordinates' => 'A beosztottak célvezetője kötelező a választott stratégiához.',
                    ]);
                }
                $targetSupervisorId = $targetForSubordinates;
            }

            foreach ($directSubordinates as $directSubordinateId) {
                if ($targetSupervisorId === null) {
                    continue;
                }
                $plan[] = ['employee_id' => (int) $directSubordinateId, 'supervisor_employee_id' => (int) $targetSupervisorId];
            }

            if ($directSubordinates === []) {
                $warnings[] = 'A vezetőnek nincs közvetlen beosztottja az adott napon.';
            }

            return $plan;
        }

        if ($mode === self::MODE_MOVE_SUBORDINATES_ONLY) {
            if ($targetForSubordinates === null) {
                throw ValidationException::withMessages([
                    'target_supervisor_for_subordinates' => 'A célvezető megadása kötelező.',
                ]);
            }

            foreach ($directSubordinates as $directSubordinateId) {
                $plan[] = ['employee_id' => (int) $directSubordinateId, 'supervisor_employee_id' => $targetForSubordinates];
            }

            if ($directSubordinates === []) {
                $warnings[] = 'A forrásvezetőnek nincs közvetlen beosztottja az adott napon.';
            }

            return $plan;
        }

        throw ValidationException::withMessages([
            'mode' => 'Nem támogatott áthelyezési mód.',
        ]);
    }

    /**
     * @return list<array{employee_id:int,first_id:int,second_id:int}>
     */
    private function detectOverlaps(int $companyId): array
    {
        $rows = $this->employeeSupervisorRepository->listCompanyHistoryRows($companyId);
        $byEmployee = [];
        foreach ($rows as $row) {
            $employeeId = (int) $row['employee_id'];
            if (! isset($byEmployee[$employeeId])) {
                $byEmployee[$employeeId] = [];
            }
            $byEmployee[$employeeId][] = $row;
        }

        $overlaps = [];
        foreach ($byEmployee as $employeeId => $employeeRows) {
            $previous = null;
            foreach ($employeeRows as $row) {
                if ($previous !== null && $this->isOverlap($previous, $row)) {
                    $overlaps[] = [
                        'employee_id' => (int) $employeeId,
                        'first_id' => (int) $previous['id'],
                        'second_id' => (int) $row['id'],
                    ];
                }
                $previous = $row;
            }
        }

        return $overlaps;
    }

    /**
     * @param array{id:int,employee_id:int,supervisor_employee_id:int,valid_from:string,valid_to:?string} $a
     * @param array{id:int,employee_id:int,supervisor_employee_id:int,valid_from:string,valid_to:?string} $b
     */
    private function isOverlap(array $a, array $b): bool
    {
        $aFrom = CarbonImmutable::parse($a['valid_from']);
        $aTo = $a['valid_to'] !== null ? CarbonImmutable::parse($a['valid_to']) : CarbonImmutable::parse('9999-12-31');
        $bFrom = CarbonImmutable::parse($b['valid_from']);
        $bTo = $b['valid_to'] !== null ? CarbonImmutable::parse($b['valid_to']) : CarbonImmutable::parse('9999-12-31');

        return $aFrom->lessThanOrEqualTo($bTo) && $bFrom->lessThanOrEqualTo($aTo);
    }

    /**
     * @param list<int> $employeeIds
     * @param list<array{employee_id:int,supervisor_employee_id:int}> $edges
     * @return list<array<int>>
     */
    private function detectCycles(array $employeeIds, array $edges): array
    {
        $adjacency = [];
        foreach ($employeeIds as $employeeId) {
            $adjacency[(int) $employeeId] = [];
        }
        foreach ($edges as $edge) {
            $adjacency[(int) $edge['employee_id']][] = (int) $edge['supervisor_employee_id'];
        }

        $cycles = [];
        $state = [];
        $stack = [];

        $visit = function (int $node) use (&$visit, &$state, &$stack, &$cycles, $adjacency): void {
            $state[$node] = 1;
            $stack[] = $node;

            foreach ($adjacency[$node] ?? [] as $next) {
                $nextState = $state[$next] ?? 0;
                if ($nextState === 0) {
                    $visit($next);
                    continue;
                }

                if ($nextState === 1) {
                    $startAt = array_search($next, $stack, true);
                    if ($startAt !== false) {
                        $cycles[] = array_slice($stack, (int) $startAt);
                    }
                }
            }

            array_pop($stack);
            $state[$node] = 2;
        };

        foreach ($employeeIds as $employeeId) {
            $node = (int) $employeeId;
            if (($state[$node] ?? 0) === 0) {
                $visit($node);
            }
        }

        return $cycles;
    }
}
