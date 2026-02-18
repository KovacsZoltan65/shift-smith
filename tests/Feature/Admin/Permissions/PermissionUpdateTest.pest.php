<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Cache\CacheVersionService;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('megtagadja az engedélyfrissítést, ha a felhasználónak nincs engedélye', function (): void {
    /** @var User $user */
    $user = User::factory()->create();
    $user->assignRole('user');

    /** @var Permission $permission */
    $permission = Permission::create([
        'name' => 'permissions.update_deny_' . uniqid(),
        'guard_name' => 'web',
    ]);

    $this
        ->actingAs($user)
        ->putJson(route('admin.permissions.update', ['id' => $permission->id]), [
            'name' => 'permissions.updated_' . uniqid(),
            'guard_name' => 'web',
        ])
        ->assertForbidden();
});

it('frissítéskor ellenőrzi a kötelező mezőket', function (): void {
    $user = $this->createAdminUser();

    /** @var Permission $permission */
    $permission = Permission::create([
        'name' => 'permissions.update_validate_' . uniqid(),
        'guard_name' => 'web',
    ]);

    $this
        ->actingAs($user)
        ->putJson(route('admin.permissions.update', ['id' => $permission->id]), [
            'name' => '',
            'guard_name' => 'web',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('lehetővé teszi az adminisztrátor számára az engedélyek frissítését és a gyorsítótár verzióinak módosítását', function (): void {
    $user = $this->createAdminUser();

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $versioner = app(CacheVersionService::class);

    /** @var Permission $permission */
    $permission = Permission::create([
        'name' => 'permissions.old_' . uniqid(),
        'guard_name' => 'web',
    ]);

    Cache::forever('v:permissions.fetch', 1);
    Cache::forever('v:selectors.permissions', 1);

    $payload = [
        'name' => 'permissions.new_' . uniqid(),
        'guard_name' => 'web',
    ];

    $this
        ->actingAs($user)
        ->putJson(route('admin.permissions.update', ['id' => $permission->id]), $payload)
        ->assertOk();
    
    $this->assertDatabaseHas('permissions', [
        'id' => $permission->id,
        'name' => $payload['name'],
        'guard_name' => 'web',
    ]);

    expect($versioner->get('permissions.fetch'))->toBe(2);
    expect($versioner->get('selectors.permissions'))->toBe(2);
});

it('lehetővé teszi ugyanazon név megtartását frissítéskor (egyedi, figyelmen kívül hagyja az aktuális azonosítót)', function (): void {
    $user = $this->createAdminUser();

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $name = 'permissions.same_' . uniqid();

    /** @var Permission $permission */
    $permission = Permission::create([
        'name' => $name,
        'guard_name' => 'web',
    ]);

    $payload = [
        'name' => $name,       // ugyanaz marad
        'guard_name' => 'web',
    ];

    $this
        ->actingAs($user)
        ->putJson(route('admin.permissions.update', ['id' => $permission->id]), $payload)
        ->assertOk();
    
    /*
    $response = $this->actingAs($user)
        ->putJson(route('admin.permissions.update', ['id' => $permission->id]), $payload);

    $response->dump();
    $response->dumpHeaders();
    expect($response->status())->toBe(200);
    */
});
