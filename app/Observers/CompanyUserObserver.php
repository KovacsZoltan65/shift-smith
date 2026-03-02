<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Services\Selectors\CompanySelectorService;
use Illuminate\Support\Facades\DB;

final class CompanyUserObserver
{
    public function __construct(
        private readonly CompanySelectorService $selectorService,
    ) {}

    public function created(CompanyUser $pivot): void
    {
        $this->bumpForCompany($pivot);
    }

    public function updated(CompanyUser $pivot): void
    {
        $this->bumpForCompany($pivot);
    }

    public function deleted(CompanyUser $pivot): void
    {
        $this->bumpForCompany($pivot);
    }

    private function bumpForCompany(CompanyUser $pivot): void
    {
        $tenantGroupId = Company::query()
            ->whereKey((int) $pivot->company_id)
            ->value('tenant_group_id');

        if (! is_numeric($tenantGroupId) || (int) $tenantGroupId <= 0) {
            return;
        }

        DB::afterCommit(function () use ($tenantGroupId): void {
            $this->selectorService->bumpSelectorVersionForTenant((int) $tenantGroupId);
        });
    }
}
