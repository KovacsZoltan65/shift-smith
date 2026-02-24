<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\Company;
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
