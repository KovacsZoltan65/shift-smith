<?php

declare(strict_types=1);

namespace App\Data\Audit;

use Spatie\LaravelData\Data;

class AuditReportData extends Data
{
    /**
     * @param array{ok:int,warn:int,fail:int,total:int} $summary
     * @param array<int, AuditCheckResultData> $checks
     * @param array<int, int> $tenant_ids
     */
    public function __construct(
        public array $summary,
        public array $checks,
        public array $tenant_ids,
        public bool $fix,
        public bool $verbose,
        public string $generated_at,
    ) {}
}
