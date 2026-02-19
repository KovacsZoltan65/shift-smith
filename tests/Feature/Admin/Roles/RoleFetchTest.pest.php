<?php

declare(strict_types=1);

use App\Models\Admin\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('átirányítja a vendégeket a bejelentkezéshez a szerepkörök lekéréséhez', function (): void {
    $this->get(route('admin.roles.fetch'))->assertRedirect();
});

it('megtagadja a lekérés engedélyét, ha a felhasználónak nincs viewAny jogosultsága', function (): void {
    $user = $this->createAdminUser();
    $user->syncRoles([]);
    $user->syncPermissions([]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $this->actingAs($user)
        ->getJson(route('admin.roles.fetch', ['order' => 'desc']))
        ->assertForbidden();
});

it('lehetővé teszi az adminisztrátor számára a szerepkörök lekérését meta + rendezés használatával', function (): void {
    $user = $this->createAdminUser();

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    // legyen biztosan több rekord
    Role::query()->firstOrCreate(['name' => 'zz_role_alpha_' . uniqid(), 'guard_name' => 'web']);
    Role::query()->firstOrCreate(['name' => 'zz_role_beta_' . uniqid(), 'guard_name' => 'web']);

    $expectedTotal = Role::query()->count();

    $resp = $this
        ->actingAs($user)
        ->getJson(route('admin.roles.fetch', [
            'page' => 1,
            'per_page' => 10,
            'field' => 'id',
            'order' => 'desc',
        ]));

    $resp
        ->assertOk()
        ->assertJsonStructure([
            'data',
            'meta' => ['current_page', 'per_page', 'total', 'last_page'],
            'filter',
        ]);

    expect($resp->json('data'))->toBeArray();
    expect($resp->json('meta.total'))->toBe($expectedTotal);
});

it('támogatja a keresést, és alapértelmezés szerint azonosító szerint (id desc) rendez', function (): void {
    $user = $this->createAdminUser();

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $token = 'zz_role_search_' . uniqid();
    Role::query()->firstOrCreate(['name' => $token, 'guard_name' => 'web']);

    $resp = $this
        ->actingAs($user)
        ->getJson(route('admin.roles.fetch', [
            'search' => $token,
            'page' => 1,
            'per_page' => 10,
        ]));

    $resp->assertOk();

    $items = $resp->json('data');
    expect($items)->toBeArray();
    
    $names = collect($items)->pluck('name');

    expect(
        $names->contains(fn ($name) => str_contains($name, $token))
    )->toBeTrue();
    
});
