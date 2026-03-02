<?php

declare(strict_types=1);

namespace App\Services\Leave\Rules;

use App\Data\Leave\CarryOverResult;
use App\Data\Leave\LeaveBalanceContext;
use App\Interfaces\LeaveCarryOverRuleInterface;
use Carbon\CarbonImmutable;

class EmployeeBlockedRule implements LeaveCarryOverRuleInterface
{
    public function applies(LeaveBalanceContext $context): bool
    {
        return $context->remaining_minutes > 0 && $context->employee_blocked_periods !== [];
    }

    public function calculate(LeaveBalanceContext $context): CarryOverResult
    {
        $validUntil = collect($context->employee_blocked_periods)
            ->map(function (array $period): CarbonImmutable {
                $date = $period['end_date'] ?? $period['start_date'] ?? null;

                return CarbonImmutable::parse((string) $date);
            })
            ->sort()
            ->last()
            ?->addDays(60)
            ?->toDateString();

        return new CarryOverResult(
            transferable_minutes: $context->remaining_minutes,
            must_expire_minutes: 0,
            valid_until: $validUntil,
            rule_applied: 'employee_blocked',
        );
    }
}
