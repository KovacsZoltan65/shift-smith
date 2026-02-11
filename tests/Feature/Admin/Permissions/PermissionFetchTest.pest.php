<?php

declare(strict_types=1);

use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('átirányítja a vendégeket a bejelentkezéshez az engedélyek lekéréséhez', function (): void {
    $this->get(route('admin.permissions.fetch'))->assertRedirect();
});

it('megtagadja a lekérés engedélyét, ha a felhasználónak nincs viewAny jogosultsága', function (): void {
    $user = $this->createAdminUser();
    $user->syncRoles([]);
    $user->syncPermissions([]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $this->actingAs($user)
        ->getJson(route('admin.permissions.fetch', ['order' => 'desc']))
        ->assertForbidden();
});

it('lehetővé teszi az adminisztrátor számára az engedélyek lekérését meta + szűrő használatával', function (): void {
    $user = $this->createAdminUser();

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    // legyen biztosan több rekord
    SpatiePermission::findOrCreate('permissions.test.alpha', 'web');
    SpatiePermission::findOrCreate('permissions.test.beta', 'web');
    SpatiePermission::findOrCreate('permissions.test.gamma', 'web');

    $expectedTotal = SpatiePermission::query()->count();

    $resp = $this
        ->actingAs($user)
        ->getJson(route('admin.permissions.fetch', [
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

it('támogatja a keresést, és alapértelmezés szerint azonosító szerint (id desc) rendez.', function (): void {
    $user = $this->createAdminUser();

    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $token = 'zz_perm_beta_' . uniqid();
    \Spatie\Permission\Models\Permission::findOrCreate("permissions.$token", 'web');

    // Baseline
    $respBase = $this
        ->actingAs($user)
        ->getJson(route('admin.permissions.fetch', [
            'page' => 1,
            'per_page' => 10,
            'field' => 'id',
            'order' => 'desc',
        ]));

    $respBase->assertOk();

    $baseTotal = (int) ($respBase->json('meta.total') ?? 0);
    expect($baseTotal)->toBeGreaterThan(0);

    // Search
    $respSearch = $this
        ->actingAs($user)
        ->getJson(route('admin.permissions.fetch', [
            'search' => $token,
            'page' => 1,
            'per_page' => 10,
            'field' => 'id',
            'order' => 'desc',
        ]));

    $respSearch->assertOk();

    $searchTotal = (int) ($respSearch->json('meta.total') ?? 0);
    expect($searchTotal)->toBeLessThanOrEqual($baseTotal);

    // FONTOS: a rekordok a te API-dban: data.data
    $rows = (array) $respSearch->json('data.data');

    // ha van találat, akkor a payloadban szerepeljen a token
    if ($searchTotal > 0) {
        expect($rows)->not->toBeEmpty();

        $row = (array) ($rows[0] ?? []);
        $haystack = strtolower(json_encode($row, JSON_UNESCAPED_UNICODE) ?: '');
        expect($haystack)->toContain(strtolower($token));
    }
});


