<?php

namespace App\Observers;

use App\Models\Company;
use App\Services\Cache\CacheInvalidatorService;

final class CompanyObserver
{
    public function __construct(
        private readonly CacheInvalidatorService $invalidator
    ) {}

    public function created(Company $company): void   { $this->invalidator->bumpCompaniesSelect(); }
    public function updated(Company $company): void   { $this->invalidator->bumpCompaniesSelect(); }
    public function deleted(Company $company): void   { $this->invalidator->bumpCompaniesSelect(); }
    public function restored(Company $company): void  { $this->invalidator->bumpCompaniesSelect(); }
    public function forceDeleted(Company $company): void { $this->invalidator->bumpCompaniesSelect(); }
}
