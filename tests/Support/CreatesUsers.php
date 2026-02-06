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
        // NOTE:
        // This project does not have a `users.company_id` column (yet).
        // Some older snippets / modules may have assumed it, but the tests
        // must match the current schema.
        //
        // We keep the optional `$company` argument so call sites stay stable,
        // but we don't persist any company reference on the user.
        $company ??= Company::factory()->create();

        /** @var User $user */
        $user = User::factory()->create();

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