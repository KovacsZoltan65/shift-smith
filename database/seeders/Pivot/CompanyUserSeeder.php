<?php

declare(strict_types=1);

namespace Database\Seeders\Pivot;

use App\Models\Company;
use App\Models\TenantGroup;
use App\Models\User;
use Illuminate\Database\Seeder;

final class CompanyUserSeeder extends Seeder
{
    public function run(): void
    {
        TenantGroup::query()
            ->orderBy('id')
            ->each(function (TenantGroup $tenant): void {
                $tenant->makeCurrent();

                try {
                    $companies = Company::query()
                        ->where('tenant_group_id', (int) $tenant->id)
                        ->where('active', true)
                        ->orderBy('id')
                        ->get(['id']);

                    if ($companies->isEmpty()) {
                        return;
                    }

                    $companyIds = $companies
                        ->pluck('id')
                        ->map(static fn ($id): int => (int) $id)
                        ->values();

                    User::query()
                        ->orderBy('id')
                        ->get()
                        ->reject(fn (User $user): bool => $this->isSuperadmin($user))
                        ->each(function (User $user) use ($companyIds): void {
                            $assignedCompanyIds = $companyIds
                                ->take(3)
                                ->all();

                            if ($assignedCompanyIds === []) {
                                return;
                            }

                            $user->companies()->syncWithoutDetaching($assignedCompanyIds);
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
