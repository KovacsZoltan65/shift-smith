<?php

declare(strict_types=1);

namespace App\Interfaces;

use App\Data\Leave\CarryOverResult;
use App\Data\Leave\LeaveBalanceContext;

interface LeaveCarryOverRuleInterface
{
    public function applies(LeaveBalanceContext $context): bool;

    public function calculate(LeaveBalanceContext $context): CarryOverResult;
}
