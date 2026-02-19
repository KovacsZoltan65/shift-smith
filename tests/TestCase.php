<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\CreatesUsers;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
    use CreatesUsers;

    public User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Inertia oldalak teszteléséhez ne függjünk a Vite build manifesttől.
        $this->withoutVite();

        // A tesztek nem az activity log működését ellenőrzik, így ezt globálisan kikapcsoljuk.
        if (function_exists('activity')) {
            activity()->disableLogging();
        }

        // Csak Feature tesztekben legyen automatikus superadmin user
        if (!str_contains(static::class, 'Tests\\Feature\\')) {
            return;
        }

        // Egyes auth tesztekben a permission/activity sémák nem minden esetben állnak rendelkezésre.
        if (!Schema::hasTable('roles') || !Schema::hasTable('users')) {
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
