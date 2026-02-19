<?php

declare(strict_types=1);

use App\Models\Admin\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('megtagadja a jogosultság részletek lekérését, ha nincs view jogosultság', function (): void {
    $user = $this->createAdminUser();
    $user->syncRoles([]);
    $user->syncPermissions([]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $permission = Permission::query()->firstOrCreate([
        'name' => 'zz_permission_show_' . uniqid(),
        'guard_name' => 'web',
    ]);

    $this
        ->actingAs($user)
        ->getJson(route('admin.permissions.by_id', ['id' => $permission->id]))
        ->assertForbidden();
});

it('visszaadja a jogosultság részleteit id alapján DTO struktúrában', function (): void {
    $user = $this->createAdminUser();

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $permission = Permission::query()->firstOrCreate([
        'name' => 'zz_permission_show_' . uniqid(),
        'guard_name' => 'web',
    ]);

    $resp = $this
        ->actingAs($user)
        ->getJson(route('admin.permissions.by_id', ['id' => $permission->id]));

    $resp
        ->assertOk()
        ->assertJsonStructure([
            'message',
            'data' => ['id', 'name', 'guard_name', 'created_at', 'updated_at'],
        ]);

    expect($resp->json('data.id'))->toBe($permission->id);
});

it('visszaadja a jogosultság részleteit név alapján DTO struktúrában', function (): void {
    $user = $this->createAdminUser();

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $permission = Permission::query()->firstOrCreate([
        'name' => 'zz_permission_name_' . uniqid(),
        'guard_name' => 'web',
    ]);

    $resp = $this
        ->actingAs($user)
        ->getJson(route('admin.permissions.by_name', ['name' => $permission->name]));

    $resp
        ->assertOk()
        ->assertJsonStructure([
            'message',
            'data' => ['id', 'name', 'guard_name'],
        ]);

    expect($resp->json('data.name'))->toBe($permission->name);
});
