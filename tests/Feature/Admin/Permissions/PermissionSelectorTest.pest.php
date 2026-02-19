<?php

declare(strict_types=1);

use App\Models\Admin\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('átirányítja a vendégeket a bejelentkezéshez a jogosultság selectorhoz', function (): void {
    $this->get(route('admin.selectors.permissions'))->assertRedirect();
});

it('bejelentkezett felhasználóként engedi a selector lekérését explicit permission jogosultság nélkül is', function (): void {
    $user = $this->createAdminUser();
    $user->syncRoles([]);
    $user->syncPermissions([]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $this
        ->actingAs($user)
        ->getJson(route('admin.selectors.permissions'))
        ->assertOk();
});

it('visszaadja a jogosultság selector listát (id + name)', function (): void {
    $user = $this->createAdminUser();

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    Permission::query()->firstOrCreate([
        'name' => 'zz_selector_permission_' . uniqid(),
        'guard_name' => 'web',
    ]);

    $resp = $this
        ->actingAs($user)
        ->getJson(route('admin.selectors.permissions'));

    $resp
        ->assertOk()
        ->assertJsonIsArray();

    $first = $resp->json()[0] ?? null;
    expect($first)->toBeArray();
    expect($first)->toHaveKeys(['id', 'name']);
});
