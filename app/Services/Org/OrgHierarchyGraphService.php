<?php

declare(strict_types=1);

namespace App\Services\Org;

use App\Data\Org\OrgHierarchyEdgeData;
use App\Data\Org\OrgHierarchyGraphData;
use App\Data\Org\OrgHierarchyNodeData;
use App\Models\Employee;
use App\Repositories\Org\OrgHierarchyRepositoryInterface;
use App\Services\Cache\CacheNamespaces;
use App\Services\Cache\CacheVersionService;
use App\Services\CacheService;
use App\Services\TenantContext;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

final class OrgHierarchyGraphService
{
    public function __construct(
        private readonly OrgHierarchyRepositoryInterface $repository,
        private readonly CacheService $cacheService,
        private readonly CacheVersionService $cacheVersionService,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function getGraph(int $companyId, ?int $rootEmployeeId, CarbonInterface $atDate, int $depth = 1): OrgHierarchyGraphData
    {
        $tenantGroupId = $this->tenantContext->currentTenantGroupIdOrFail();
        $base = CacheNamespaces::tenantOrgHierarchy($tenantGroupId, $companyId);
        $ymd = CarbonImmutable::instance($atDate)->toDateString();
        $version = $this->cacheVersionService->get("{$base}:hierarchy");
        $rootKey = $rootEmployeeId !== null ? (string) $rootEmployeeId : 'ceo';
        $effectiveDepth = max(1, $depth);

        /** @var OrgHierarchyGraphData $graph */
        $graph = $this->cacheService->remember(
            tag: $base,
            key: "{$base}:hierarchy:{$rootKey}:d{$effectiveDepth}:{$ymd}:{$version}",
            callback: fn (): OrgHierarchyGraphData => $this->buildGraph($companyId, $rootEmployeeId, $atDate, $effectiveDepth),
            ttl: (int) config('cache.ttl_fetch', 300)
        );

        return $graph;
    }

    public function getNode(int $companyId, int $employeeId, CarbonInterface $atDate): ?OrgHierarchyNodeData
    {
        $employee = $this->repository->findEmployeeInCompany($companyId, $employeeId);
        if (! $employee instanceof Employee) {
            return null;
        }

        $count = $this->getSubordinateCount($companyId, $employeeId, $atDate);

        return $this->toNode($employee, $count, $count);
    }

    public function getSubordinateCount(int $companyId, int $employeeId, CarbonInterface $atDate): int
    {
        $counts = $this->repository->getDirectSubordinateCounts($companyId, [$employeeId], $atDate);
        return (int) ($counts[$employeeId] ?? 0);
    }

    /**
     * @return array<int, array{id:int,full_name:string,email:string|null,position:string|null}>
     */
    public function searchEmployees(int $companyId, string $query, int $limit = 20): array
    {
        $term = trim($query);
        if ($term === '') {
            return [];
        }

        $tenantGroupId = $this->tenantContext->currentTenantGroupIdOrFail();
        $base = CacheNamespaces::tenantOrgHierarchy($tenantGroupId, $companyId);
        $safeLimit = max(1, min($limit, 50));
        $hash = hash('sha256', mb_strtolower($term, 'UTF-8').'|'.$safeLimit);

        /** @var array<int, array{id:int,full_name:string,email:string|null,position:string|null}> $rows */
        $rows = $this->cacheService->remember(
            tag: $base,
            key: "{$base}:employee_search:{$hash}",
            callback: fn (): array => $this->repository->searchEmployeesForHierarchy($companyId, $term, $safeLimit),
            ttl: 60
        );

        return $rows;
    }

    private function buildGraph(int $companyId, ?int $rootEmployeeId, CarbonInterface $atDate, int $depth): OrgHierarchyGraphData
    {
        $root = $rootEmployeeId !== null
            ? $this->repository->findEmployeeInCompany($companyId, $rootEmployeeId)
            : $this->repository->findCeo($companyId);

        if (! $root instanceof Employee && $rootEmployeeId !== null) {
            $root = $this->repository->findCeo($companyId);
        }

        if (! $root instanceof Employee) {
            return new OrgHierarchyGraphData(
                nodes: [],
                edges: [],
                meta: [
                    'root_id' => null,
                    'company_id' => $companyId,
                    'at_date' => CarbonImmutable::instance($atDate)->toDateString(),
                    'depth' => $depth,
                    'empty' => true,
                ]
            );
        }

        $nodeModels = [(int) $root->id => $root];
        $edges = [];
        $visited = [(int) $root->id => true];
        $frontier = [(int) $root->id];
        $level = 0;

        while ($level < $depth && $frontier !== []) {
            $next = [];

            foreach ($frontier as $supervisorId) {
                $children = $this->repository->listDirectSubordinates($companyId, $supervisorId, $atDate);
                foreach ($children as $child) {
                    $childId = (int) $child->id;
                    $nodeModels[$childId] = $child;
                    $edges[] = new OrgHierarchyEdgeData(
                        source: $supervisorId,
                        target: $childId,
                    );

                    if (! isset($visited[$childId])) {
                        $visited[$childId] = true;
                        $next[] = $childId;
                    }
                }
            }

            $frontier = array_values(array_unique($next));
            $level++;
        }

        $nodeIds = array_keys($nodeModels);
        $counts = $this->repository->getDirectSubordinateCounts($companyId, $nodeIds, $atDate);
        $nodes = [];
        foreach ($nodeModels as $nodeId => $employee) {
            $nodes[] = $this->toNode(
                $employee,
                (int) ($counts[$nodeId] ?? 0),
                (int) ($counts[$nodeId] ?? 0),
            );
        }

        return new OrgHierarchyGraphData(
            nodes: $nodes,
            edges: $edges,
            meta: [
                'root_id' => (int) $root->id,
                'company_id' => $companyId,
                'at_date' => CarbonImmutable::instance($atDate)->toDateString(),
                'depth' => $depth,
                'empty' => false,
            ]
        );
    }

    private function toNode(Employee $employee, int $directCount, int $totalCount): OrgHierarchyNodeData
    {
        $label = trim((string) (($employee->last_name ?? '').' '.($employee->first_name ?? '')));
        if ($label === '') {
            $label = (string) $employee->first_name;
        }

        return new OrgHierarchyNodeData(
            id: (int) $employee->id,
            label: $label,
            position: $employee->position?->name,
            org_level: (string) ($employee->org_level ?? Employee::ORG_LEVEL_STAFF),
            direct_count: $directCount,
            total_count: $totalCount,
        );
    }
}
