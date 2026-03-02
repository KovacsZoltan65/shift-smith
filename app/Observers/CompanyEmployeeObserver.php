<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Company;
use App\Models\CompanyEmployee;
use App\Services\Selectors\CompanySelectorService;
use Illuminate\Support\Facades\DB;

final class CompanyEmployeeObserver
{
    public function __construct(
        private readonly CompanySelectorService $selectorService,
    ) {}

    public function created(CompanyEmployee $pivot): void
    {
        $this->bumpForCompany($pivot);
    }

    public function updated(CompanyEmployee $pivot): void
    {
        $this->bumpForCompany($pivot);
    }

    public function deleted(CompanyEmployee $pivot): void
    {
        $this->bumpForCompany($pivot);
    }

    private function bumpForCompany(CompanyEmployee $pivot): void
    {
        $tenantGroupId = Company::query()
            ->whereKey((int) $pivot->company_id)
            ->value('tenant_group_id');

        if (! is_numeric($tenantGroupId)) {
            return;
        }

        $tenantId = (int) $tenantGroupId;
        if ($tenantId <= 0) {
            return;
        }

        DB::afterCommit(function () use ($tenantId): void {
            $this->selectorService->bumpSelectorVersionForTenant($tenantId);
        });
    }
}
