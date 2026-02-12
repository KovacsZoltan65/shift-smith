<?php

declare(strict_types=1);

use App\Models\Admin\Permission;
use App\Models\Admin\Role;
use App\Services\Cache\CacheVersionService;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('megtagadja a szerepkör frissítését, ha nincs jogosultság', function (): void {
    $user = $this->createAdminUser();
    $user->syncRoles([]);
    $user->syncPermissions([]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $role = Role::query()->firstOrCreate(['name' => 'zz_upd_role_' . uniqid(), 'guard_name' => 'web']);

    $this
        ->actingAs($user)
        ->putJson(route('admin.roles.update', ['id' => $role->id]), [
            'name' => 'x',
            'guard_name' => 'web',
        ])
        ->assertForbidden();
});

it('validálja a kötelező mezőket frissítéskor', function (): void {
    $user = $this->createAdminUser();

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $role = Role::query()->firstOrCreate(['name' => 'zz_upd_role_' . uniqid(), 'guard_name' => 'web']);

    $this
        ->actingAs($user)
        ->putJson(route('admin.roles.update', ['id' => $role->id]), [
            'name' => '',
            'guard_name' => '',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'guard_name']);
});

it('lehetővé teszi adminnak a szerepkör frissítését permission sync-kel és cache bump-pal', function (): void {
    $user = $this->createAdminUser();

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $versioner = app(CacheVersionService::class);

    Cache::forever('v:roles.fetch', 1);
    Cache::forever('v:selectors.roles', 1);

    $role = Role::query()->firstOrCreate(['name' => 'zz_upd_role_' . uniqid(), 'guard_name' => 'web']);

    $permA = Permission::query()->where('name', 'employees.viewAny')->first();
    $permB = Permission::query()->where('name', 'companies.viewAny')->first();
    expect($permA)->not()->toBeNull();
    expect($permB)->not()->toBeNull();

    // Kezdő állapot: A
    $role->syncPermissions([$permA->id]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $payload = [
        'name' => $role->name . '_renamed',
        'guard_name' => 'web',
        'permission_ids' => [$permB->id],
    ];

    $resp = $this
        ->actingAs($user)
        ->putJson(route('admin.roles.update', ['id' => $role->id]), $payload);

    $resp->assertOk();

    $role->refresh();

    expect($role->name)->toBe($payload['name']);
    expect($role->permissions()->pluck('id')->all())->toContain($permB->id);
    expect($role->permissions()->pluck('id')->all())->not()->toContain($permA->id);

    expect($versioner->get('roles.fetch'))->toBe(2);
    expect($versioner->get('selectors.roles'))->toBe(2);
});
