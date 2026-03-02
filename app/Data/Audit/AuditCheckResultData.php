<?php

declare(strict_types=1);

namespace App\Data\Audit;

use Spatie\LaravelData\Data;

class AuditCheckResultData extends Data
{
    /**
     * @param array<int, array<string, scalar|null>> $sample_rows
     */
    public function __construct(
        public string $id,
        public string $title,
        public string $severity,
        public string $entity,
        public int $count,
        public array $sample_rows,
        public string $hint,
    ) {}
}
