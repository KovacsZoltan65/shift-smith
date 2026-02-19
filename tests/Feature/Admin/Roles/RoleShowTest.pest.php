<?php

declare(strict_types=1);

use App\Models\Admin\Permission;
use App\Models\Admin\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('megtagadja a szerepkör részletek lekérését, ha nincs view jogosultság', function (): void {
    $user = $this->createAdminUser();
    $user->syncRoles([]);
    $user->syncPermissions([]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $role = Role::query()->firstOrCreate(['name' => 'zz_show_role_' . uniqid(), 'guard_name' => 'web']);

    $this
        ->actingAs($user)
        ->getJson(route('admin.roles.by_id', ['id' => $role->id]))
        ->assertForbidden();
});

it('visszaadja a szerepkör részleteit id alapján DTO struktúrában', function (): void {
    $user = $this->createAdminUser();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $permission = Permission::query()->where('name', 'employees.viewAny')->firstOrFail();

    $role = Role::query()->firstOrCreate(['name' => 'zz_show_role_' . uniqid(), 'guard_name' => 'web']);
    $role->syncPermissions([$permission->id]);
    $role->refresh();

    $resp = $this
        ->actingAs($user)
        ->getJson(route('admin.roles.by_id', ['id' => $role->id]));

    $resp
        ->assertOk()
        ->assertJsonStructure([
            'message',
            'data' => ['id', 'name', 'guard_name', 'permission_ids', 'created_at', 'updated_at'],
        ]);

    expect($resp->json('data.id'))->toBe($role->id);
    expect($resp->json('data.permission_ids'))->toContain($permission->id);
});

it('visszaadja a szerepkör részleteit név alapján DTO struktúrában', function (): void {
    $user = $this->createAdminUser();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $role = Role::query()->firstOrCreate(['name' => 'zz_show_name_' . uniqid(), 'guard_name' => 'web']);

    $resp = $this
        ->actingAs($user)
        ->getJson(route('admin.roles.by_name', ['name' => $role->name]));

    $resp
        ->assertOk()
        ->assertJsonStructure([
            'message',
            'data' => ['id', 'name', 'guard_name', 'permission_ids'],
        ]);

    expect($resp->json('data.name'))->toBe($role->name);
});
