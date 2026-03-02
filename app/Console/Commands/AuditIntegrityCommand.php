<?php

/*
php artisan audit:integrity
php artisan audit:integrity --tenant=12 --tenant=15
php artisan audit:integrity --json
*/

declare(strict_types=1);

namespace App\Console\Commands;

use App\Data\Audit\AuditCheckResultData;
use App\Services\Audit\IntegrityAuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class AuditIntegrityCommand extends Command
{
    protected $signature = 'audit:integrity {--tenant=*} {--fix} {--json} {--all} {--only-fail} {--only-warn}';

    protected $description = 'Runs a read-only integrity audit over tenant, company and pivot data.';

    public function __construct(
        private readonly IntegrityAuditService $integrityAuditService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $report = $this->integrityAuditService->run(
            tenantIds: (array) $this->option('tenant'),
            fix: (bool) $this->option('fix'),
            verbose: $this->output->isVerbose(),
        );

        $relativePath = sprintf('audits/integrity-%s.json', now()->format('Ymd-His'));
        $absolutePath = storage_path('app/'.$relativePath);

        File::ensureDirectoryExists(dirname($absolutePath));
        File::put(
            $absolutePath,
            json_encode($report->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        $payload = [
            ...$report->toArray(),
            'report_path' => $absolutePath,
        ];

        if ((bool) $this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return $report->summary['fail'] > 0 ? self::FAILURE : self::SUCCESS;
        }

        $this->info(sprintf(
            'Integrity audit finished. OK=%d WARN=%d FAIL=%d TOTAL=%d',
            $report->summary['ok'],
            $report->summary['warn'],
            $report->summary['fail'],
            $report->summary['total'],
        ));

        $rows = $this->filterChecks(collect($report->checks))
            ->map(fn (AuditCheckResultData $check): array => [
                $this->statusFor($check),
                $check->id,
                strtoupper($check->severity),
                $check->entity,
                $check->count,
                $check->title,
            ])
            ->all();

        if ($rows !== []) {
            $this->table(['Status', 'ID', 'Severity', 'Entity', 'Count', 'Title'], $rows);
        } else {
            $this->line('No checks matched the selected output filter.');
        }

        $problemRows = collect($report->checks)
            ->filter(fn (AuditCheckResultData $check): bool => $check->count > 0)
            ->map(static fn (AuditCheckResultData $check): array => [
                $check->id,
                strtoupper($check->severity),
                $check->count,
                $check->hint,
            ])
            ->values()
            ->all();

        if ($problemRows !== []) {
            $this->newLine();
            $this->warn('Problem checks');
            $this->table(['ID', 'Severity', 'Count', 'Hint'], $problemRows);
        }

        if ($this->output->isVerbose()) {
            foreach ($this->filterChecks(collect($report->checks))->all() as $check) {
                if ($check->count === 0) {
                    continue;
                }

                $this->newLine();
                $this->line(sprintf('[%s] %s', $check->id, $check->title));
                $this->line('Hint: '.$check->hint);
                $this->line('Sample rows:');
                $this->line(json_encode($check->sample_rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            }
        }

        $this->newLine();
        $this->line('JSON report saved to: '.$absolutePath);

        if ((bool) $this->option('fix')) {
            $this->comment('No automatic P0 fixes were applied. The current command is read-only for these checks.');
        }

        return $report->summary['fail'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function statusFor(AuditCheckResultData $check): string
    {
        if ($check->count === 0) {
            return 'PASS';
        }

        return $check->severity === 'fail' ? 'FAIL' : 'WARN';
    }

    /**
     * @param \Illuminate\Support\Collection<int, AuditCheckResultData> $checks
     * @return \Illuminate\Support\Collection<int, AuditCheckResultData>
     */
    private function filterChecks(\Illuminate\Support\Collection $checks): \Illuminate\Support\Collection
    {
        if ((bool) $this->option('only-fail')) {
            return $checks->filter(static fn (AuditCheckResultData $check): bool => $check->count > 0 && $check->severity === 'fail')->values();
        }

        if ((bool) $this->option('only-warn')) {
            return $checks->filter(static fn (AuditCheckResultData $check): bool => $check->count > 0 && $check->severity === 'warn')->values();
        }

        if ((bool) $this->option('all')) {
            return $checks->values();
        }

        return $checks->filter(static fn (AuditCheckResultData $check): bool => $check->count > 0)->values();
    }
}
