<?php

declare(strict_types=1);

use App\Models\Admin\Role;
use App\Services\Cache\CacheVersionService;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('megtagadja a szerepkör törlését, ha nincs jogosultság', function (): void {
    $user = $this->createAdminUser();
    $user->syncRoles([]);
    $user->syncPermissions([]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $role = Role::query()->firstOrCreate(['name' => 'zz_del_role_' . uniqid(), 'guard_name' => 'web']);

    $this
        ->actingAs($user)
        ->deleteJson(route('admin.roles.destroy', ['id' => $role->id]))
        ->assertForbidden();
});

it('lehetővé teszi adminnak a szerepkör törlését és bumpolja a cache verziókat', function (): void {
    $user = $this->createAdminUser();

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $versioner = app(CacheVersionService::class);

    Cache::forever('v:roles.fetch', 1);
    Cache::forever('v:selectors.roles', 1);

    $role = Role::query()->firstOrCreate(['name' => 'zz_del_role_' . uniqid(), 'guard_name' => 'web']);

    $this
        ->actingAs($user)
        ->deleteJson(route('admin.roles.destroy', ['id' => $role->id]))
        ->assertOk();

    $this->assertDatabaseMissing('roles', ['id' => $role->id]);

    expect($versioner->get('roles.fetch'))->toBe(2);
    expect($versioner->get('selectors.roles'))->toBe(2);
});
