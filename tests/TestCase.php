<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    public User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Csak Feature tesztekben legyen automatikus superadmin user
        if (!str_contains(static::class, 'Tests\\Feature\\')) {
            return;
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::findOrCreate('superadmin', 'web');
        Role::findOrCreate('admin', 'web');

        $this->user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $this->user->assignRole('superadmin');
    }
}
