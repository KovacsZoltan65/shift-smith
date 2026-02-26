<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Cache\CacheVersionService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('megtagadja a törlési engedélyt, ha a felhasználónak nincs engedélye', function (): void {
    /** @var User $user */
    $user = $this->createAdminUser();
    $user->syncRoles([]);
    $user->syncPermissions([]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user = $user->refresh();

    /** @var Permission $permission */
    $permission = Permission::create([
        'name' => 'perm.delete.deny_' . uniqid(),
        'guard_name' => 'web',
    ]);

    $this
        ->actingAs($user)
        ->deleteJson(route('admin.permissions.destroy', ['id' => $permission->id]))
        ->assertForbidden();
});

it('lehetővé teszi az adminisztrátor számára az engedélyek törlését és a gyorsítótár verzióinak módosítását', function (): void {
    /** @var User $user */
    $user = $this->createSuperadminUser();

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user = $user->refresh();

    $versioner = app(CacheVersionService::class);
    $permissionsFetchBefore = $versioner->get('permissions.fetch');
    $permissionsSelectorBefore = $versioner->get('selectors.permissions');

    /** @var Permission $permission */
    $permission = Permission::create([
        'name' => 'perm.delete.ok_' . uniqid(),
        'guard_name' => 'web',
    ]);

    $this
        ->actingAs($user)
        ->deleteJson(route('admin.permissions.destroy', ['id' => $permission->id]))
        ->assertOk()
        ->assertJson([
            'deleted' => true,
        ]);
    
    // Spatie Permission alapból nem softdelete-ol, hanem hard delete
    $this->assertDatabaseMissing('permissions', ['id' => $permission->id]);

    expect($versioner->get('permissions.fetch'))->toBeGreaterThan($permissionsFetchBefore);
    expect($versioner->get('selectors.permissions'))->toBeGreaterThan($permissionsSelectorBefore);
});
