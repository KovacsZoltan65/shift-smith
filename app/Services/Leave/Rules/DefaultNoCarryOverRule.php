<?php

declare(strict_types=1);

namespace App\Services\Leave\Rules;

use App\Data\Leave\CarryOverResult;
use App\Data\Leave\LeaveBalanceContext;
use App\Interfaces\LeaveCarryOverRuleInterface;

class DefaultNoCarryOverRule implements LeaveCarryOverRuleInterface
{
    public function applies(LeaveBalanceContext $context): bool
    {
        return true;
    }

    public function calculate(LeaveBalanceContext $context): CarryOverResult
    {
        return new CarryOverResult(
            transferable_minutes: 0,
            must_expire_minutes: max(0, $context->remaining_minutes),
            valid_until: null,
            rule_applied: 'default_no_carry_over',
        );
    }
}
