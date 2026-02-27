<?php

declare(strict_types=1);

namespace Database\Seeders\Pivot;

use App\Models\Company;
use App\Models\TenantGroup;
use App\Models\User;
use App\Models\UserEmployee;
use Illuminate\Database\Seeder;

final class UserEmployeeSeeder extends Seeder
{
    public function run(): void
    {
        TenantGroup::query()
            ->orderBy('id')
            ->each(function (TenantGroup $tenant): void {
                $tenant->makeCurrent();

                try {
                    User::query()
                        ->orderBy('id')
                        ->get()
                        ->reject(fn (User $user): bool => $this->isSuperadmin($user))
                        ->each(function (User $user) use ($tenant): void {
                            $companies = $user->companies()
                                ->where('companies.tenant_group_id', (int) $tenant->id)
                                ->where('companies.active', true)
                                ->orderBy('companies.id')
                                ->get(['companies.id', 'companies.name']);

                            foreach ($companies as $company) {
                                $email = trim((string) $user->email);

                                $employee = $company->employees()
                                    ->where('company_employee.active', true)
                                    ->when(
                                        $email !== '',
                                        fn ($query) => $query->where('employees.email', $email)
                                    )
                                    ->orderBy('employees.id')
                                    ->first();

                                if ($employee === null) {
                                    $employee = $company->employees()
                                        ->where('company_employee.active', true)
                                        ->orderBy('employees.id')
                                        ->first();
                                }

                                if ($employee === null) {
                                    continue;
                                }

                                UserEmployee::query()->updateOrCreate(
                                    [
                                        'user_id' => (int) $user->id,
                                        'company_id' => (int) $company->id,
                                    ],
                                    [
                                        'employee_id' => (int) $employee->id,
                                        'active' => true,
                                    ]
                                );
                            }
                        });
                } finally {
                    TenantGroup::forgetCurrent();
                }
            });
    }

    private function isSuperadmin(User $user): bool
    {
        if (method_exists($user, 'hasRole') && $user->hasRole('superadmin')) {
            return true;
        }

        return strcasecmp((string) $user->email, (string) config('seeding.superadmin_email', 'superadmin@shift-smith.com')) === 0;
    }
}
