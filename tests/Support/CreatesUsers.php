<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\CompanyEmployee;
use App\Models\Company;
use App\Models\Employee;
use App\Models\UserEmployee;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

trait CreatesUsers
{
    protected function seedRolesAndPermissions(): void
    {
        // Minimal seed for Spatie roles/permissions used by policies.
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    protected function createAdminUser(?Company $company = null): User
    {
        $company ??= Company::factory()->create();

        /** @var User $user */
        $user = User::factory()->create();

        $user->companies()->syncWithoutDetaching([$company->id]);
        $user->assignRole('admin');

        $employee = Employee::factory()->create([
            'company_id' => (int) $company->id,
        ]);

        CompanyEmployee::query()->updateOrCreate(
            [
                'company_id' => (int) $company->id,
                'employee_id' => (int) $employee->id,
            ],
            [
                'active' => true,
            ]
        );

        UserEmployee::query()->updateOrCreate(
            [
                'user_id' => (int) $user->id,
                'company_id' => (int) $company->id,
                'employee_id' => (int) $employee->id,
            ],
            [
                'active' => true,
            ]
        );

        return $user;
    }

    protected function createSuperadminUser(): User
    {
        /** @var User $user */
        $user = User::factory()->create();

        $user->assignRole('superadmin');

        return $user;
    }
}
