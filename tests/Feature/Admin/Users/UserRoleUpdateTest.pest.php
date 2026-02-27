<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Cache\CacheVersionService;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('megtagadja a user role frissítését, ha nincs jogosultság', function (): void {
    $admin = $this->createAdminUser();
    $admin->syncRoles([]);
    $admin->syncPermissions([]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $admin->refresh();

    $target = User::factory()->create();
    $role = Role::findByName('operator', 'web');

    $this->actingAs($admin)
        ->patchJson(route('admin.users.role.update', ['user' => $target->id]), [
            'role_id' => $role->id,
        ])
        ->assertForbidden();
});

it('lehetővé teszi adminnak a felhasználó primary role frissítését és pontosan egy role marad', function (): void {
    $admin = $this->createAdminUser();
    $admin->givePermissionTo('users.assignRoles');
    $target = User::factory()->create();
    $target->assignRole('user');
    $target->assignRole('operator');

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $admin->refresh();
    $target->refresh();

    $versioner = app(CacheVersionService::class);
    Cache::forever('v:users.fetch', 1);
    Cache::forever('v:selectors.users', 1);
    Cache::forever('v:roles.fetch', 1);
    Cache::forever('v:selectors.roles', 1);
    Cache::forever('v:dashboard.stats', 1);

    $role = Role::findByName('admin', 'web');

    $this->actingAs($admin)
        ->patchJson(route('admin.users.role.update', ['user' => $target->id]), [
            'role_id' => $role->id,
        ])
        ->assertOk()
        ->assertJsonPath('data.primary_role_name', 'admin');

    $target->refresh();
    $targetRoleNames = $target->roles()->pluck('name')->values()->all();

    expect($targetRoleNames)->toBe(['admin']);
    expect($versioner->get('users.fetch'))->toBe(2);
    expect($versioner->get('selectors.users'))->toBe(2);
    expect($versioner->get('roles.fetch'))->toBe(2);
    expect($versioner->get('selectors.roles'))->toBe(2);
});
