<?php

declare(strict_types=1);

namespace App\Data\Scheduling\AutoPlan;

use Spatie\LaravelData\Data;

class GenerateInputData extends Data
{
    /**
     * @param list<int> $employee_ids
     * @param list<DemandItemData> $weekday_demand
     * @param list<DemandItemData> $weekend_demand
     */
    public function __construct(
        public string $month,
        public array $employee_ids,
        public array $weekday_demand,
        public array $weekend_demand,
        public ?GenerateRulesData $rules,
    ) {}

    /**
     * @param array{
     *   month:string,
     *   employee_ids:list<int>,
     *   demand:array{
     *     weekday:list<array{shift_id:int,required_count:int}>,
     *     weekend:list<array{shift_id:int,required_count:int}>
     *   },
     *   rules?:array{
     *     min_rest_hours?:int|null,
     *     max_consecutive_days?:int|null,
     *     weekend_fairness?:bool|null
     *   }
     * } $payload
     */
    public static function fromPayload(array $payload): self
    {
        return new self(
            month: (string) $payload['month'],
            employee_ids: array_values(array_map('intval', $payload['employee_ids'])),
            weekday_demand: array_values(array_map(
                static fn (array $row): DemandItemData => DemandItemData::from($row),
                $payload['demand']['weekday'] ?? []
            )),
            weekend_demand: array_values(array_map(
                static fn (array $row): DemandItemData => DemandItemData::from($row),
                $payload['demand']['weekend'] ?? []
            )),
            rules: isset($payload['rules'])
                ? GenerateRulesData::from($payload['rules'])
                : null,
        );
    }
}
