<?php

declare(strict_types=1);

namespace App\Data\Org;

use Spatie\LaravelData\Data;

final class OrgHierarchyEdgeData extends Data
{
    public function __construct(
        public int $source,
        public int $target,
    ) {
    }
}
