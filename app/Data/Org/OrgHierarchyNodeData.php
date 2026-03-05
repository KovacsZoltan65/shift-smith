<?php

declare(strict_types=1);

namespace App\Data\Org;

use Spatie\LaravelData\Data;

final class OrgHierarchyNodeData extends Data
{
    public function __construct(
        public int $id,
        public string $label,
        public ?string $position,
        public string $org_level,
        public int $direct_count,
        public int $total_count,
    ) {
    }
}
