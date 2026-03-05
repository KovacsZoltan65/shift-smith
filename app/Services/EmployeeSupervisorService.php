<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Org\EmployeeSupervisor;
use App\Repositories\EmployeeSupervisorRepositoryInterface;
use App\Services\Cache\CacheNamespaces;
use App\Services\Cache\CacheVersionService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class EmployeeSupervisorService
{
    public function __construct(
        private readonly EmployeeSupervisorRepositoryInterface $employeeSupervisorRepository,
        private readonly HierarchyIntegrityService $hierarchyIntegrityService,
        private readonly CacheVersionService $cacheVersionService,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function assignSupervisor(
        int $companyId,
        int $employeeId,
        int $supervisorEmployeeId,
        string $validFrom,
        ?int $actorUserId = null
    ): EmployeeSupervisor {
        $validFromDate = CarbonImmutable::parse($validFrom)->startOfDay();

        return DB::transaction(function () use ($companyId, $employeeId, $supervisorEmployeeId, $validFromDate, $actorUserId): EmployeeSupervisor {
            $closed = $this->employeeSupervisorRepository->closeActivePeriod($companyId, $employeeId, $validFromDate);

            $this->hierarchyIntegrityService->validateNewSupervisorRelationOrFail(
                companyId: $companyId,
                employeeId: $employeeId,
                supervisorEmployeeId: $supervisorEmployeeId,
                validFrom: $validFromDate,
                ignoreId: $closed?->id !== null ? (int) $closed->id : null,
                enforceOverlap: false,
            );

            $created = $this->employeeSupervisorRepository->createNewRelation(
                companyId: $companyId,
                employeeId: $employeeId,
                supervisorEmployeeId: $supervisorEmployeeId,
                validFrom: $validFromDate,
                createdByUserId: $actorUserId
            );

            DB::afterCommit(function () use ($companyId, $supervisorEmployeeId, $validFromDate): void {
                $tenantGroupId = $this->tenantContext->currentTenantGroupIdOrFail();
                $base = CacheNamespaces::tenantOrgHierarchy($tenantGroupId, $companyId);
                $this->cacheVersionService->bump("{$base}:hierarchy");
                $this->cacheVersionService->bump("{$base}:subtree:{$supervisorEmployeeId}:".$validFromDate->toDateString());
            });

            return $created;
        });
    }

    /**
     * @return list<array{
     *   id:int,
     *   valid_from:string,
     *   valid_to:?string,
     *   supervisor_employee_id:int,
     *   supervisor_name:string
     * }>
     */
    public function history(int $companyId, int $employeeId): array
    {
        return collect($this->employeeSupervisorRepository->listSupervisorHistory($companyId, $employeeId))
            ->map(static function (EmployeeSupervisor $row): array {
                $supervisorName = trim((string) (($row->supervisorEmployee?->last_name ?? '').' '.($row->supervisorEmployee?->first_name ?? '')));

                return [
                    'id' => (int) $row->id,
                    'valid_from' => (string) $row->valid_from?->format('Y-m-d'),
                    'valid_to' => $row->valid_to?->format('Y-m-d'),
                    'supervisor_employee_id' => (int) $row->supervisor_employee_id,
                    'supervisor_name' => $supervisorName,
                ];
            })
            ->values()
            ->all();
    }
}
