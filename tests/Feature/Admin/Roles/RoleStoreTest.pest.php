<?php

declare(strict_types=1);

use App\Models\Admin\Permission;
use App\Models\Admin\Role;
use App\Models\User;
use App\Services\Cache\CacheVersionService;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('megtagadja a szerepkör létrehozását, ha a felhasználónak nincs jogosultsága', function (): void {
    /** @var User $user */
    $user = User::factory()->create();
    $user->assignRole('user');

    $this
        ->actingAs($user)
        ->postJson(route('admin.roles.store'), ['name' => 'Nope', 'guard_name' => 'web'])
        ->assertForbidden();

    $this->assertDatabaseMissing('roles', ['name' => 'Nope']);
});

it('validálja a kötelező mezőket létrehozáskor', function (): void {
    $user = $this->createAdminUser();

    $this
        ->actingAs($user)
        ->postJson(route('admin.roles.store'), ['name' => '', 'guard_name' => ''])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'guard_name']);
});

it('lehetővé teszi adminnak a szerepkör létrehozását, permission sync-kel és cache bump-pal', function (): void {
    $user = $this->createAdminUser();

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $versioner = app(CacheVersionService::class);

    Cache::forever('v:roles.fetch', 1);
    Cache::forever('v:selectors.roles', 1);

    $perm = Permission::query()->where('name', 'employees.viewAny')->first();
    expect($perm)->not()->toBeNull();

    $payload = [
        'name' => 'test_role_' . uniqid(),
        'guard_name' => 'web',
        'permission_ids' => [$perm->id],
    ];

    $resp = $this
        ->actingAs($user)
        ->postJson(route('admin.roles.store'), $payload);

    $resp->assertOk();

    $roleId = $resp->json('id');
    expect($roleId)->toBeInt();

    /** @var Role $role */
    $role = Role::query()->findOrFail($roleId);
    expect($role->permissions()->pluck('id')->all())->toContain($perm->id);

    // cache version bump
    expect($versioner->get('roles.fetch'))->toBe(2);
    expect($versioner->get('selectors.roles'))->toBe(2);
});
