<?php

declare(strict_types=1);

namespace App\Services\Employee;

use App\Interfaces\EmployeeRepositoryInterface;
use App\Models\Employee;
use App\Repositories\EmployeeSupervisorRepositoryInterface;
use App\Repositories\Org\OrgHierarchyRepositoryInterface;
use App\Services\Cache\CacheNamespaces;
use App\Services\Cache\CacheVersionService;
use App\Services\HierarchyIntegrityService;
use App\Services\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class EmployeeDeletionService
{
    public const STRATEGY_NONE = 'none';
    public const STRATEGY_REASSIGN_OLD = 'reassign_to_old_supervisor';
    public const STRATEGY_REASSIGN_SPECIFIC = 'reassign_to_specific_supervisor';

    public function __construct(
        private readonly EmployeeRepositoryInterface $employeeRepository,
        private readonly EmployeeSupervisorRepositoryInterface $employeeSupervisorRepository,
        private readonly OrgHierarchyRepositoryInterface $orgHierarchyRepository,
        private readonly HierarchyIntegrityService $hierarchyIntegrityService,
        private readonly CacheVersionService $cacheVersionService,
        private readonly TenantContext $tenantContext,
    ) {
    }

    /**
     * @return array{
     *   meta: array{company_id:int,employee_id:int,effective_from:string,strategy:string},
     *   employee: array{id:int,name:string,position:?string},
     *   subordinate_count:int,
     *   affected_employee_ids:list<int>,
     *   affected_count:int,
     *   warnings:list<string>,
     *   errors:list<string>
     * }
     */
    public function previewDelete(
        int $companyId,
        int $employeeId,
        ?string $effectiveFrom = null,
        string $strategy = self::STRATEGY_NONE,
        ?int $targetSupervisorId = null,
    ): array {
        $effectiveDate = CarbonImmutable::parse($effectiveFrom ?? now()->toDateString())->startOfDay();

        $employee = $this->employeeRepository->findByIdInCompany($employeeId, $companyId);
        if (! $employee instanceof Employee) {
            throw ValidationException::withMessages([
                'employee_id' => 'A dolgozó nem található az aktuális cégben.',
            ]);
        }

        $warnings = [];
        $errors = [];

        $subordinateIds = $this->employeeSupervisorRepository->listDirectSubordinates($companyId, $employeeId, $effectiveDate);
        $activeSupervisor = $this->employeeSupervisorRepository->findActiveSupervisor($companyId, $employeeId, $effectiveDate);

        try {
            $this->validateDeletionPlan(
                companyId: $companyId,
                employee: $employee,
                effectiveFrom: $effectiveDate,
                strategy: $strategy,
                targetSupervisorId: $targetSupervisorId,
                directSubordinateIds: $subordinateIds,
                activeSupervisorEmployeeId: $activeSupervisor?->supervisor_employee_id !== null
                    ? (int) $activeSupervisor->supervisor_employee_id
                    : null,
                warnings: $warnings,
            );
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $messages) {
                foreach ($messages as $message) {
                    $errors[] = (string) $message;
                }
            }
        }

        return [
            'meta' => [
                'company_id' => $companyId,
                'employee_id' => $employeeId,
                'effective_from' => $effectiveDate->toDateString(),
                'strategy' => $strategy,
            ],
            'employee' => [
                'id' => (int) $employee->id,
                'name' => (string) $employee->name,
                'position' => $employee->position?->name,
            ],
            'subordinate_count' => count($subordinateIds),
            'affected_employee_ids' => array_values($subordinateIds),
            'affected_count' => count($subordinateIds),
            'warnings' => array_values(array_unique($warnings)),
            'errors' => array_values(array_unique($errors)),
        ];
    }

    /**
     * @return array{
     *   success:bool,
     *   deleted_employee_id:int,
     *   affected_count:int,
     *   cache_version:int
     * }
     */
    public function deleteEmployee(
        int $companyId,
        int $employeeId,
        ?string $effectiveFrom = null,
        string $strategy = self::STRATEGY_NONE,
        ?int $targetSupervisorId = null,
        ?int $actorUserId = null,
    ): array {
        $preview = $this->previewDelete(
            companyId: $companyId,
            employeeId: $employeeId,
            effectiveFrom: $effectiveFrom,
            strategy: $strategy,
            targetSupervisorId: $targetSupervisorId,
        );

        if ($preview['errors'] !== []) {
            throw ValidationException::withMessages([
                'delete' => $preview['errors'],
            ]);
        }

        $effectiveDate = CarbonImmutable::parse($preview['meta']['effective_from'])->startOfDay();

        DB::transaction(function () use ($companyId, $employeeId, $effectiveDate, $strategy, $targetSupervisorId, $actorUserId): void {
            /** @var Employee|null $employee */
            $employee = $this->employeeRepository->findByIdInCompanyForUpdate($employeeId, $companyId);
            if (! $employee instanceof Employee) {
                throw ValidationException::withMessages([
                    'employee_id' => 'A dolgozó nem található az aktuális cégben.',
                ]);
            }

            $subordinateIds = $this->employeeSupervisorRepository->listDirectSubordinates($companyId, $employeeId, $effectiveDate);
            $activeSupervisor = $this->employeeSupervisorRepository->findActiveSupervisor($companyId, $employeeId, $effectiveDate);
            $activeSupervisorEmployeeId = $activeSupervisor?->supervisor_employee_id !== null
                ? (int) $activeSupervisor->supervisor_employee_id
                : null;
            $warnings = [];

            $this->validateDeletionPlan(
                companyId: $companyId,
                employee: $employee,
                effectiveFrom: $effectiveDate,
                strategy: $strategy,
                targetSupervisorId: $targetSupervisorId,
                directSubordinateIds: $subordinateIds,
                activeSupervisorEmployeeId: $activeSupervisorEmployeeId,
                warnings: $warnings,
            );

            if ($subordinateIds !== []) {
                $nextSupervisorId = $strategy === self::STRATEGY_REASSIGN_OLD
                    ? $activeSupervisorEmployeeId
                    : $targetSupervisorId;

                foreach ($subordinateIds as $subordinateId) {
                    if (! \is_int($nextSupervisorId) || $nextSupervisorId <= 0) {
                        continue;
                    }

                    $this->employeeSupervisorRepository->closeActivePeriod($companyId, $subordinateId, $effectiveDate);
                    $this->employeeSupervisorRepository->createNewRelation(
                        companyId: $companyId,
                        employeeId: $subordinateId,
                        supervisorEmployeeId: $nextSupervisorId,
                        validFrom: $effectiveDate,
                        createdByUserId: $actorUserId,
                    );
                }
            }

            $this->employeeSupervisorRepository->closeActivePeriod($companyId, $employeeId, $effectiveDate);
            $this->employeeRepository->softDeleteEmployee($companyId, $employeeId);
        });

        $cacheVersion = $this->invalidateCaches($companyId, $employeeId, $effectiveDate);

        return [
            'success' => true,
            'deleted_employee_id' => $employeeId,
            'affected_count' => (int) $preview['affected_count'],
            'cache_version' => $cacheVersion,
        ];
    }

    /**
     * @param list<int> $directSubordinateIds
     * @param list<string> $warnings
     */
    private function validateDeletionPlan(
        int $companyId,
        Employee $employee,
        CarbonImmutable $effectiveFrom,
        string $strategy,
        ?int $targetSupervisorId,
        array $directSubordinateIds,
        ?int $activeSupervisorEmployeeId,
        array &$warnings,
    ): void {
        $subordinateCount = count($directSubordinateIds);

        if ($employee->org_level === Employee::ORG_LEVEL_CEO && $subordinateCount > 0) {
            throw ValidationException::withMessages([
                'employee_id' => 'A CEO nem törölhető, amíg aktív beosztottjai vannak.',
            ]);
        }

        if ($subordinateCount === 0) {
            return;
        }

        if ($strategy === self::STRATEGY_NONE) {
            throw ValidationException::withMessages([
                'strategy' => 'Aktív beosztottakkal rendelkező vezető csak áthelyezési stratégiával törölhető.',
            ]);
        }

        $newSupervisorId = null;

        if ($strategy === self::STRATEGY_REASSIGN_OLD) {
            if ($activeSupervisorEmployeeId === null) {
                throw ValidationException::withMessages([
                    'strategy' => 'A dolgozónak nincs aktív felettese, ezért a beosztottak nem rendezhetők át a jelenlegi feletteshez.',
                ]);
            }

            $newSupervisorId = $activeSupervisorEmployeeId;
        }

        if ($strategy === self::STRATEGY_REASSIGN_SPECIFIC) {
            if ($targetSupervisorId === null) {
                throw ValidationException::withMessages([
                    'target_supervisor_employee_id' => 'A cél vezető megadása kötelező.',
                ]);
            }

            if ($targetSupervisorId === (int) $employee->id) {
                throw ValidationException::withMessages([
                    'target_supervisor_employee_id' => 'A cél vezető nem lehet a törlendő dolgozó.',
                ]);
            }

            $newSupervisorId = $targetSupervisorId;
        }

        if (! \is_int($newSupervisorId) || $newSupervisorId <= 0) {
            throw ValidationException::withMessages([
                'strategy' => 'Érvénytelen áthelyezési stratégia.',
            ]);
        }

        foreach ($directSubordinateIds as $subordinateId) {
            $activeRelation = $this->employeeSupervisorRepository->findActiveSupervisor($companyId, $subordinateId, $effectiveFrom);

            $this->hierarchyIntegrityService->validateNewSupervisorRelationOrFail(
                companyId: $companyId,
                employeeId: $subordinateId,
                supervisorEmployeeId: $newSupervisorId,
                validFrom: $effectiveFrom,
                ignoreId: $activeRelation?->id !== null ? (int) $activeRelation->id : null,
                enforceOverlap: true,
            );
        }

        $leaderNames = $this->orgHierarchyRepository
            ->getEmployeesByIdsInCompany($companyId, $directSubordinateIds)
            ->filter(static fn (Employee $row): bool => $row->org_level !== Employee::ORG_LEVEL_STAFF)
            ->pluck('name')
            ->filter()
            ->values()
            ->all();

        if ($leaderNames !== []) {
            $warnings[] = 'A közvetlen beosztott vezetők a saját csapatukat megtartva kerülnek át az új felettes alá.';
        }
    }

    private function invalidateCaches(int $companyId, int $employeeId, CarbonImmutable $effectiveFrom): int
    {
        $tenantGroupId = $this->tenantContext->currentTenantGroupIdOrFail();
        $base = CacheNamespaces::tenantOrgHierarchy($tenantGroupId, $companyId);

        $this->cacheVersionService->bump('employees.fetch');
        $this->cacheVersionService->bump('selectors.employees');
        $hierarchyVersion = $this->cacheVersionService->bump("{$base}:hierarchy");
        $this->cacheVersionService->bump("{$base}:subtree:{$employeeId}:".$effectiveFrom->toDateString());

        return $hierarchyVersion;
    }
}
