<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('megtagadja az engedély létrehozását, ha a felhasználónak nincs engedélye', function (): void {
    /** @var User $user */
    $user = User::factory()->create();
    $user->assignRole('user');

    $this
        ->actingAs($user)
        ->postJson(
            route('admin.permissions.store'), 
            ['name' => 'permissions.nope', 'guard_name' => 'web']
        )
        ->assertForbidden();

    $this->assertDatabaseMissing('permissions', ['name' => 'permissions.nope']);
});

it('érvényesíti a kötelező mezőket a tároláskor', function (): void {
    $user = $this->createAdminUser();

    $this
        ->actingAs($user)
        ->postJson(
            route('admin.permissions.store'), 
            ['name' => '', 'guard_name' => '']
        )
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'guard_name']);
});

it('lehetővé teszi az adminisztrátor számára az engedélyek tárolását', function (): void {
    $user = $this->createAdminUser();

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $payload = [
        'name' => 'permissions.test.create_' . uniqid(),
        'guard_name' => 'web',
    ];
/*
    $response = $this
        ->actingAs($user)
        ->postJson(route('admin.permissions.store'), $payload);

    $response->dump();           // response body
    $response->dumpHeaders();    // ha redirect / content-type gyanús

    expect($response->status())->toBe(200);
*/
    
    $this
        ->actingAs($user)
        ->postJson(
            route('admin.permissions.store'), 
            $payload
        )
        ->assertOk();
    

    $this->assertDatabaseHas('permissions', [
        'name' => $payload['name'],
        'guard_name' => 'web',
    ]);
});

it('egyedi nevet kényszerít ki a nem soft-deleted engedélyek között', function (): void {
    $user = $this->createAdminUser();

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $name = 'permissions.unique_' . uniqid();

    // első létrehozás
    SpatiePermission::findOrCreate($name, 'web');

    // duplikált létrehozás legyen 422
    $this
        ->actingAs($user)
        ->postJson(
            route('admin.permissions.store'), 
            [
                'name' => $name,
                'guard_name' => 'web',
            ]
        )
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);

    /**
     * Megjegyzés:
     * A Spatie permissions tábla alapból NEM soft delete-os.
     * Ha nálad mégis soft delete van (custom), akkor itt lehetne "delete() után engedje".
     * Alap Spatie esetén ezt a részt nem érdemes erőltetni.
     */
});
