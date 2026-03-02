<?php

declare(strict_types=1);

namespace App\Observers;

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
        $tenantGroupId = $pivot->company()
            ->withoutGlobalScopes()
            ->value('tenant_group_id');

        if (! is_numeric($tenantGroupId) || (int) $tenantGroupId <= 0) {
            return;
        }

        DB::afterCommit(function () use ($tenantGroupId): void {
            $this->selectorService->bumpSelectorVersionForTenant((int) $tenantGroupId);
        });
    }
}
