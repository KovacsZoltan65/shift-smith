<?php

declare(strict_types=1);

namespace Database\Seeders\Pivot;

use App\Models\Company;
use App\Models\CompanyEmployee;
use App\Models\Employee;
use App\Models\TenantGroup;
use Illuminate\Database\Seeder;

class CompanyEmployeeSeeder extends Seeder
{
    public function run(): void
    {
        TenantGroup::query()
            ->orderBy('id')
            ->each(function (TenantGroup $tenant): void {
                $tenant->makeCurrent();

                try {
                    $activeCompanies = Company::query()
                        ->where('tenant_group_id', (int) $tenant->id)
                        ->where('active', true)
                        ->orderBy('id')
                        ->get(['id']);

                    if ($activeCompanies->isEmpty()) {
                        if (app()->environment('local')) {
                            logger()->info('seed.company_employee.skip_no_active_company', [
                                'tenant_group_id' => (int) $tenant->id,
                            ]);
                        }

                        return;
                    }

                    $activeCompanyIds = $activeCompanies->pluck('id')->map(static fn ($id): int => (int) $id)->all();
                    $fallbackCompanyId = (int) $activeCompanies->first()->id;

                    // A) employees.company_id alapján (ha a cég az aktuális tenant aktív cégei között van).
                    Employee::query()
                        ->whereIn('company_id', $activeCompanyIds)
                        ->orderBy('id')
                        ->each(function (Employee $employee): void {
                            CompanyEmployee::query()->updateOrCreate(
                                [
                                    'company_id' => (int) $employee->company_id,
                                    'employee_id' => (int) $employee->id,
                                ],
                                [
                                    'active' => true,
                                ]
                            );
                        });

                    // B) company_id NULL fallback: tenant első aktív company-ja.
                    Employee::query()
                        ->whereNull('company_id')
                        ->whereDoesntHave('companies')
                        ->orderBy('id')
                        ->each(function (Employee $employee) use ($fallbackCompanyId, $tenant): void {
                            CompanyEmployee::query()->updateOrCreate(
                                [
                                    'company_id' => $fallbackCompanyId,
                                    'employee_id' => (int) $employee->id,
                                ],
                                [
                                    'active' => true,
                                ]
                            );

                            if (app()->environment('local')) {
                                logger()->info('seed.company_employee.fallback_null_company_id', [
                                    'tenant_group_id' => (int) $tenant->id,
                                    'employee_id' => (int) $employee->id,
                                    'fallback_company_id' => $fallbackCompanyId,
                                ]);
                            }
                        });
                } finally {
                    TenantGroup::forgetCurrent();
                }
            });
    }
}

