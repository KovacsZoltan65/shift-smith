<?php

declare(strict_types=1);

use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('megtagadja a tömeges törlést, ha a felhasználónak nincs engedélye', function (): void {
    // legyen egy user, akinek nincs se role, se permission
    $user = $this->createAdminUser();
    $user->syncRoles([]);
    $user->syncPermissions([]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $perms = [
        Permission::create(['name' => 'perm.bulkdeny.a_' . uniqid(), 'guard_name' => 'web']),
        Permission::create(['name' => 'perm.bulkdeny.b_' . uniqid(), 'guard_name' => 'web']),
    ];

    $this
        ->actingAs($user)
        ->deleteJson(route('admin.permissions.destroy_bulk'), [
            'ids' => collect($perms)->pluck('id')->all(),
        ])
        ->assertForbidden();
});

it('lehetővé teszi az adminisztrátor számára a jogosultságok tömeges törlését', function (): void {
    $user = $this->createSuperadminUser();

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $perms = [
        Permission::create(['name' => 'perm.bulk.a_' . uniqid(), 'guard_name' => 'web']),
        Permission::create(['name' => 'perm.bulk.b_' . uniqid(), 'guard_name' => 'web']),
        Permission::create(['name' => 'perm.bulk.c_' . uniqid(), 'guard_name' => 'web']),
    ];

    $ids = collect($perms)->pluck('id')->all();

    $this
        ->actingAs($user)
        ->deleteJson(route('admin.permissions.destroy_bulk'), ['ids' => $ids])
        ->assertOk()
        ->assertJsonStructure(['message', 'deleted']);
    
    foreach ($ids as $id) {
        $this->assertDatabaseMissing('permissions', ['id' => $id]);
    }
});

it('validálja az ids tömböt bulk törlésnél', function (): void {
    $user = $this->createAdminUser();

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $this
        ->actingAs($user)
        ->deleteJson(route('admin.permissions.destroy_bulk'), ['ids' => 'nope'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['ids']);
});
