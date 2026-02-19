<?php

declare(strict_types=1);

use App\Models\Admin\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('átirányítja a vendégeket a bejelentkezéshez a szerepkör selectorhoz', function (): void {
    $this->get(route('admin.selectors.roles'))->assertRedirect();
});

it('bejelentkezett felhasználóként engedi a selector lekérését explicit role jogosultság nélkül is', function (): void {
    $user = $this->createAdminUser();
    $user->syncRoles([]);
    $user->syncPermissions([]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $this
        ->actingAs($user)
        ->getJson(route('admin.selectors.roles'))
        ->assertOk();
});

it('visszaadja a szerepkör selector listát (id + name)', function (): void {
    $user = $this->createAdminUser();

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    Role::query()->firstOrCreate(['name' => 'zz_selector_role_' . uniqid(), 'guard_name' => 'web']);

    $resp = $this
        ->actingAs($user)
        ->getJson(route('admin.selectors.roles'));

    $resp
        ->assertOk()
        ->assertJsonIsArray();

    $first = $resp->json()[0] ?? null;
    expect($first)->toBeArray();
    expect($first)->toHaveKeys(['id', 'name']);
});
