<?php

declare(strict_types=1);

use App\Models\Admin\Role;
use App\Services\Cache\CacheVersionService;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('megtagadja a bulk törlést, ha nincs jogosultság', function (): void {
    $user = $this->createAdminUser();
    $user->syncRoles([]);
    $user->syncPermissions([]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $ids = Role::query()
        ->limit(2)
        ->pluck('id')
        ->all();

    $this
        ->actingAs($user)
        ->deleteJson(route('admin.roles.destroy_bulk'), ['ids' => $ids])
        ->assertForbidden();
});

it('lehetővé teszi adminnak a bulk törlést és bumpolja a cache verziókat', function (): void {
    $user = $this->createAdminUser();

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $versioner = app(CacheVersionService::class);

    Cache::forever('v:roles.fetch', 1);
    Cache::forever('v:selectors.roles', 1);

    $r1 = Role::query()->firstOrCreate(['name' => 'zz_bulk_role_1_' . uniqid(), 'guard_name' => 'web']);
    $r2 = Role::query()->firstOrCreate(['name' => 'zz_bulk_role_2_' . uniqid(), 'guard_name' => 'web']);
    $r3 = Role::query()->firstOrCreate(['name' => 'zz_bulk_role_3_' . uniqid(), 'guard_name' => 'web']);

    $ids = [$r1->id, $r2->id, $r3->id];

    $resp = $this
        ->actingAs($user)
        ->deleteJson(route('admin.roles.destroy_bulk'), ['ids' => $ids]);

    $resp
        ->assertOk()
        ->assertJsonStructure(['message', 'deleted']);

    foreach ($ids as $id) {
        $this->assertDatabaseMissing('roles', ['id' => $id]);
    }

    expect($versioner->get('roles.fetch'))->toBe(2);
    expect($versioner->get('selectors.roles'))->toBe(2);
});
