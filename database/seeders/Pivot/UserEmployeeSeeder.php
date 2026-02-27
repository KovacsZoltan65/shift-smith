<?php

declare(strict_types=1);

namespace Database\Seeders\Pivot;

use App\Models\Company;
use App\Models\Employee;
use App\Models\TenantGroup;
use App\Models\User;
use App\Models\UserEmployee;
use Illuminate\Database\Seeder;

class UserEmployeeSeeder extends Seeder
{
    public function run(): void
    {
        TenantGroup::query()
            ->orderBy('id')
            ->each(function (TenantGroup $tenant): void {
                $tenant->makeCurrent();

                try {
                    $tenantCompanyIds = Company::query()
                        ->where('tenant_group_id', (int) $tenant->id)
                        ->where('active', true)
                        ->orderBy('id')
                        ->pluck('id')
                        ->map(static fn ($id): int => (int) $id)
                        ->values()
                        ->all();

                    if ($tenantCompanyIds === []) {
                        return;
                    }

                    $mapped = 0;
                    $reasons = [
                        'no_email_match' => 0,
                        'superadmin_without_email_match' => 0,
                        'no_companies' => 0,
                        'no_employees' => 0,
                    ];

                    User::query()
                        ->orderBy('id')
                        ->each(function (User $user) use ($tenantCompanyIds, $tenant, &$mapped, &$reasons): void {
                            $accessibleCompanyIds = $user->companies()
                                ->whereIn('companies.id', $tenantCompanyIds)
                                ->where('companies.tenant_group_id', (int) $tenant->id)
                                ->where('companies.active', true)
                                ->orderBy('companies.id')
                                ->pluck('companies.id')
                                ->map(static fn ($id): int => (int) $id)
                                ->values()
                                ->all();

                            // 1) Email match tenanten belül (superadmin számára is engedett).
                            $email = is_string($user->email) ? trim($user->email) : '';
                            if ($email !== '') {
                                $emailMatchedEmployee = Employee::query()
                                    ->where('email', $email)
                                    ->whereHas('companies', function ($q) use ($tenant, $user, $accessibleCompanyIds): void {
                                        $q->where('companies.tenant_group_id', (int) $tenant->id)
                                            ->where('companies.active', true)
                                            ->where('company_employee.active', true)
                                            ->when(
                                                ! $user->hasRole('superadmin'),
                                                fn ($scoped) => $scoped->whereIn('companies.id', $accessibleCompanyIds)
                                            );
                                    })
                                    ->orderBy('employees.id')
                                    ->first();

                                if ($emailMatchedEmployee instanceof Employee) {
                                    UserEmployee::query()->updateOrCreate(
                                        [
                                            'user_id' => (int) $user->id,
                                            'employee_id' => (int) $emailMatchedEmployee->id,
                                        ],
                                        [
                                            'active' => true,
                                        ]
                                    );

                                    $mapped++;
                                    return;
                                }
                            }

                            $reasons['no_email_match']++;

                            // 3) Superadmin email-match nélkül kimarad.
                            if ($user->hasRole('superadmin')) {
                                $reasons['superadmin_without_email_match']++;
                                return;
                            }

                            // 2) Fallback: user tenanten belüli cégei közül legkisebb ID-jú company.
                            $fallbackCompanyId = $accessibleCompanyIds[0] ?? null;

                            if (! is_numeric($fallbackCompanyId)) {
                                $reasons['no_companies']++;
                                return;
                            }

                            $fallbackEmployee = Employee::query()
                                ->whereHas('companies', function ($q) use ($fallbackCompanyId, $tenant): void {
                                    $q->where('companies.id', (int) $fallbackCompanyId)
                                        ->where('companies.tenant_group_id', (int) $tenant->id)
                                        ->where('companies.active', true)
                                        ->where('company_employee.active', true);
                                })
                                ->orderBy('employees.id')
                                ->first();

                            if (! $fallbackEmployee instanceof Employee) {
                                $reasons['no_employees']++;
                                return;
                            }

                            UserEmployee::query()->updateOrCreate(
                                [
                                    'user_id' => (int) $user->id,
                                    'employee_id' => (int) $fallbackEmployee->id,
                                ],
                                [
                                    'active' => true,
                                ]
                            );

                            $mapped++;
                        });

                    if (app()->environment('local')) {
                        logger()->info('seed.user_employee.summary', [
                            'tenant_group_id' => (int) $tenant->id,
                            'mapped' => $mapped,
                            'unmapped' => array_sum($reasons),
                            'reasons' => $reasons,
                        ]);
                    }
                } finally {
                    TenantGroup::forgetCurrent();
                }
            });
    }
}
