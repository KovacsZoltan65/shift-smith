<?php

declare(strict_types=1);

namespace App\Services\Leave\Rules;

use App\Data\Leave\CarryOverResult;
use App\Data\Leave\LeaveBalanceContext;
use App\Interfaces\LeaveCarryOverRuleInterface;
use Carbon\CarbonImmutable;

class EmployerExceptionRule implements LeaveCarryOverRuleInterface
{
    public function applies(LeaveBalanceContext $context): bool
    {
        return $context->remaining_minutes > 0 && $context->has_employer_exception;
    }

    public function calculate(LeaveBalanceContext $context): CarryOverResult
    {
        return new CarryOverResult(
            transferable_minutes: $context->remaining_minutes,
            must_expire_minutes: 0,
            valid_until: CarbonImmutable::create($context->year + 1, 1, 1)->addDays(59)->toDateString(),
            rule_applied: 'employer_exception',
        );
    }
}
