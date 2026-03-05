<?php

declare(strict_types=1);

namespace App\Data\Org;

use Spatie\LaravelData\Data;

final class OrgHierarchyGraphData extends Data
{
    /**
     * @param array<int, OrgHierarchyNodeData> $nodes
     * @param array<int, OrgHierarchyEdgeData> $edges
     * @param array<string, mixed> $meta
     */
    public function __construct(
        public array $nodes,
        public array $edges,
        public array $meta,
    ) {
    }
}
