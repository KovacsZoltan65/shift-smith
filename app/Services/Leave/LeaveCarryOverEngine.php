<?php

declare(strict_types=1);

namespace App\Services\Leave;

use App\Data\Leave\CarryOverResult;
use App\Data\Leave\LeaveBalanceContext;
use App\Interfaces\LeaveCarryOverRuleInterface;
use App\Services\Leave\Rules\AgeBonusQuarterRule;
use App\Services\Leave\Rules\DefaultNoCarryOverRule;
use App\Services\Leave\Rules\EmployeeBlockedRule;
use App\Services\Leave\Rules\EmployerExceptionRule;
use App\Services\Leave\Rules\OctoberEntryRule;
use DomainException;

class LeaveCarryOverEngine
{
    /**
     * @param array<int, LeaveCarryOverRuleInterface>|null $rules
     */
    public function __construct(
        private readonly ?array $rules = null,
    ) {
    }

    public function evaluate(LeaveBalanceContext $context): CarryOverResult
    {
        foreach ($this->rules() as $rule) {
            if ($rule->applies($context)) {
                return $rule->calculate($context);
            }
        }

        throw new DomainException('No carry-over rule matched the leave balance context.');
    }

    /**
     * @return array<int, LeaveCarryOverRuleInterface>
     */
    private function rules(): array
    {
        if ($this->rules !== null) {
            return $this->rules;
        }

        return [
            new EmployeeBlockedRule(),
            new EmployerExceptionRule(),
            new OctoberEntryRule(),
            new AgeBonusQuarterRule(),
            new DefaultNoCarryOverRule(),
        ];
    }
}
