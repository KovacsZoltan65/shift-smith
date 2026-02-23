<?php

declare(strict_types=1);

namespace App\Data\Scheduling\AutoPlan;

use Spatie\LaravelData\Data;

class GenerateResultData extends Data
{
    /**
     * @param array{
     *   id:int,
     *   name:string,
     *   status:string,
     *   date_from:string,
     *   date_to:string
     * } $work_schedule
     * @param array{
     *   min_rest_hours:int,
     *   max_consecutive_days:int,
     *   weekend_fairness:bool
     * } $rules
     * @param array{
     *   slots_total:int,
     *   slots_filled:int,
     *   slots_missing:int,
     *   coverage_rate:float
     * } $coverage
     * @param list<array{
     *   date:string,
     *   shift_id:int,
     *   reason:string
     * }> $missing
     */
    public function __construct(
        public array $work_schedule,
        public int $assignments_created,
        public array $rules,
        public array $coverage,
        public array $missing,
        public int $generation_report_id,
    ) {}
}
