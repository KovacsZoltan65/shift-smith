<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Employee;
use App\Models\UserEmployee;
use App\Services\Selectors\CompanySelectorService;
use Illuminate\Support\Facades\DB;

final class UserEmployeeObserver
{
    public function __construct(
        private readonly CompanySelectorService $selectorService,
    ) {}

    public function created(UserEmployee $pivot): void
    {
        $this->bumpForEmployee($pivot);
    }

    public function updated(UserEmployee $pivot): void
    {
        $this->bumpForEmployee($pivot);
    }

    public function deleted(UserEmployee $pivot): void
    {
        $this->bumpForEmployee($pivot);
    }

    private function bumpForEmployee(UserEmployee $pivot): void
    {
        $employee = Employee::query()
            ->with(['companies:id,tenant_group_id', 'company:id,tenant_group_id'])
            ->find((int) $pivot->employee_id);

        if ($employee === null) {
            return;
        }

        $tenantIds = $employee->companies
            ->pluck('tenant_group_id')
            ->push($employee->company?->tenant_group_id)
            ->filter(static fn ($value): bool => is_numeric($value) && (int) $value > 0)
            ->map(static fn ($value): int => (int) $value)
            ->unique()
            ->values()
            ->all();

        foreach ($tenantIds as $tenantId) {
            DB::afterCommit(function () use ($tenantId): void {
                $this->selectorService->bumpSelectorVersionForTenant($tenantId);
            });
        }
    }
}
