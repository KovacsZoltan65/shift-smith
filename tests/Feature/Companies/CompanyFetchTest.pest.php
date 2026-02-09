<?php

declare(strict_types=1);

use App\Models\Company;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('átirányítja a vendégeket a bejelentkezéshez a cégek lekéréséhez', function (): void {
    $this->get(route('companies.fetch'))->assertRedirect();
});

it('denies companies fetch if user lacks viewAny permission', function (): void {
    $user = $this->createAdminUser();
    $user->syncRoles([]);
    $user->syncPermissions([]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $this->actingAs($user)
        ->getJson(route('companies.fetch', ['order' => 'desc']))
        ->assertForbidden();
});

it('allows admin to fetch companies with meta + filter', function (): void {
    $user = $this->createAdminUser();

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    Company::factory()->count(15)->create();

    $expectedTotal = Company::query()->count();

    $resp = $this
        ->actingAs($user)
        ->getJson(route('companies.fetch', [
            'page' => 1,
            'per_page' => 10,
            'order' => 'desc',
        ]));

    $resp
        ->assertOk()
        ->assertJsonStructure([
            'data',
            'meta' => ['current_page', 'per_page', 'total', 'last_page'],
            'filter',
        ]);

    expect($resp->json('data'))->toHaveCount(10);
    expect($resp->json('meta.total'))->toBe($expectedTotal);
});


it('supports search and defaults to sorting by id desc', function (): void {
    $user = $this->createAdminUser();

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    Company::factory()->create(['name' => 'AAA Alpha', 'email' => 'aaa@example.com']);
    Company::factory()->create(['name' => 'BBB Beta', 'email' => 'bbb@example.com']);
    $last = Company::factory()->create(['name' => 'Zzz Last', 'email' => 'last@example.com']);

    $respSearch = $this
        ->actingAs($user)
        ->getJson(route('companies.fetch', [
            'search' => 'beta',
            'page' => 1,
            'per_page' => 10,
            'order' => 'desc',
        ]));

    $respSearch->assertOk();
    expect($respSearch->json('data'))->toHaveCount(1);
    expect($respSearch->json('data.0.name'))->toBe('BBB Beta');

    $resp = $this
        ->actingAs($user)
        ->getJson(route('companies.fetch', [
            'page' => 1,
            'per_page' => 10,
            'order' => 'desc',
        ]));

    $resp->assertOk();
    expect($resp->json('data.0.id'))->toBe($last->id);
});
