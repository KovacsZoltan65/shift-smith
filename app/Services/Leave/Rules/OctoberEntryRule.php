<?php

declare(strict_types=1);

namespace App\Services\Leave\Rules;

use App\Data\Leave\CarryOverResult;
use App\Data\Leave\LeaveBalanceContext;
use App\Interfaces\LeaveCarryOverRuleInterface;
use Carbon\CarbonImmutable;

class OctoberEntryRule implements LeaveCarryOverRuleInterface
{
    public function applies(LeaveBalanceContext $context): bool
    {
        if ($context->remaining_minutes <= 0 || $context->employment_start_date === null) {
            return false;
        }

        if (! str_starts_with($context->leave_type, 'annual')) {
            return false;
        }

        $startDate = CarbonImmutable::parse($context->employment_start_date);

        return $startDate->year === $context->year
            && $startDate->greaterThanOrEqualTo(CarbonImmutable::create($context->year, 10, 1)->startOfDay());
    }

    public function calculate(LeaveBalanceContext $context): CarryOverResult
    {
        return new CarryOverResult(
            transferable_minutes: $context->remaining_minutes,
            must_expire_minutes: 0,
            valid_until: CarbonImmutable::create($context->year + 1, 3, 31)->toDateString(),
            rule_applied: 'october_entry',
        );
    }
}
