<?php

declare(strict_types=1);

namespace App\Services\Leave\Rules;

use App\Data\Leave\CarryOverResult;
use App\Data\Leave\LeaveBalanceContext;
use App\Interfaces\LeaveCarryOverRuleInterface;
use Carbon\CarbonImmutable;

class AgeBonusQuarterRule implements LeaveCarryOverRuleInterface
{
    public function applies(LeaveBalanceContext $context): bool
    {
        return $context->remaining_minutes > 0
            && $context->agreement_age_bonus_transfer
            && $context->leave_type === 'annual_age_bonus';
    }

    public function calculate(LeaveBalanceContext $context): CarryOverResult
    {
        $transferableMinutes = intdiv($context->remaining_minutes, 4);

        return new CarryOverResult(
            transferable_minutes: $transferableMinutes,
            must_expire_minutes: max(0, $context->remaining_minutes - $transferableMinutes),
            valid_until: CarbonImmutable::create($context->year + 1, 3, 31)->toDateString(),
            rule_applied: 'age_bonus_quarter',
        );
    }
}
