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

    public function getGraph(int $companyId, ?int $rootEmployeeId, CarbonInterface $atDate): OrgHierarchyGraphData
    {
        $tenantGroupId = $this->tenantContext->currentTenantGroupIdOrFail();
        $base = CacheNamespaces::tenantOrgHierarchy($tenantGroupId, $companyId);
        $ymd = CarbonImmutable::instance($atDate)->toDateString();
        $version = $this->cacheVersionService->get("{$base}:hierarchy");
        $rootKey = $rootEmployeeId !== null ? (string) $rootEmployeeId : 'ceo';

        /** @var OrgHierarchyGraphData $graph */
        $graph = $this->cacheService->remember(
            tag: $base,
            key: "{$base}:hierarchy_graph:{$rootKey}:{$ymd}:v{$version}",
            callback: fn (): OrgHierarchyGraphData => $this->buildGraph($companyId, $rootEmployeeId, $atDate),
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

    private function buildGraph(int $companyId, ?int $rootEmployeeId, CarbonInterface $atDate): OrgHierarchyGraphData
    {
        $root = $rootEmployeeId !== null
            ? $this->repository->findEmployeeInCompany($companyId, $rootEmployeeId)
            : $this->repository->findCeo($companyId);

        if (! $root instanceof Employee) {
            return new OrgHierarchyGraphData(
                nodes: [],
                edges: [],
                meta: [
                    'root_id' => null,
                    'company_id' => $companyId,
                    'at_date' => CarbonImmutable::instance($atDate)->toDateString(),
                    'empty' => true,
                ]
            );
        }

        $children = $this->repository->listDirectSubordinates($companyId, (int) $root->id, $atDate);
        $childIds = $children->pluck('id')->map(static fn ($id): int => (int) $id)->values()->all();
        $counts = $this->repository->getDirectSubordinateCounts($companyId, array_merge([(int) $root->id], $childIds), $atDate);

        $nodes = [
            $this->toNode(
                $root,
                (int) ($counts[(int) $root->id] ?? 0),
                (int) ($counts[(int) $root->id] ?? 0),
            ),
        ];

        $edges = [];
        foreach ($children as $child) {
            $childId = (int) $child->id;

            $nodes[] = $this->toNode(
                $child,
                (int) ($counts[$childId] ?? 0),
                (int) ($counts[$childId] ?? 0),
            );

            $edges[] = new OrgHierarchyEdgeData(
                source: (int) $root->id,
                target: $childId
            );
        }

        return new OrgHierarchyGraphData(
            nodes: $nodes,
            edges: $edges,
            meta: [
                'root_id' => (int) $root->id,
                'company_id' => $companyId,
                'at_date' => CarbonImmutable::instance($atDate)->toDateString(),
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
