<?php

namespace App\Observers;

use App\Models\CompanyEmployee;
use App\Models\Employee;
use App\Services\Cache\CacheInvalidatorService;

final class EmployeeObserver
{
    public function __construct(
        private readonly CacheInvalidatorService $invalidator
    ) {}

    public function created(Employee $employee): void
    {
        $this->ensureCompanyPivot($employee);
        $this->invalidator->bumpCompaniesSelect();
    }

    public function updated(Employee $employee): void
    {
        if ($employee->wasChanged('company_id')) {
            $this->ensureCompanyPivot($employee);
        }

        $this->invalidator->bumpCompaniesSelect();
    }

    public function deleted(Employee $employee): void   { $this->invalidator->bumpCompaniesSelect(); }
    public function restored(Employee $employee): void  { $this->invalidator->bumpCompaniesSelect(); }
    public function forceDeleted(Employee $employee): void { $this->invalidator->bumpCompaniesSelect(); }

    private function ensureCompanyPivot(Employee $employee): void
    {
        CompanyEmployee::query()->updateOrCreate(
            [
                'company_id' => (int) $employee->company_id,
                'employee_id' => (int) $employee->id,
            ],
            [
                'active' => true,
            ]
        );
    }
}
