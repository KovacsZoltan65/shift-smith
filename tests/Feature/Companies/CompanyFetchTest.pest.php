<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\TenantGroup;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('átirányítja a vendégeket a bejelentkezéshez a cégek lekéréséhez', function (): void {
    $this->get(route('companies.fetch'))->assertRedirect();
});

it('megtagadja a vállalatok általi lekérést, ha a felhasználónak nincs viewAny engedélye', function (): void {
    $user = $this->createAdminUser();
    $user->syncRoles([]);
    $user->syncPermissions([]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $this->actingAs($user)
        ->getJson(route('companies.fetch', ['order' => 'desc']))
        ->assertForbidden();
});

it('lehetővé teszi az adminisztrátor számára, hogy meta + szűrővel rendelkező cégeket kérjen le', function (): void {
    $user = $this->createAdminUser();
    $company = $user->companies()->firstOrFail();
    $tenantGroupId = (int) $company->tenant_group_id;

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    TenantGroup::query()->whereKey($tenantGroupId)->firstOrFail()->makeCurrent();

    Company::factory()->count(15)->create([
        'tenant_group_id' => $tenantGroupId,
    ]);

    $expectedTotal = Company::query()
        ->where('tenant_group_id', $tenantGroupId)
        ->count();

    $resp = $this
        ->actingAs($user)
        ->withSession([
            'current_company_id' => (int) $company->id,
            'current_tenant_group_id' => $tenantGroupId,
        ])
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
    $company = $user->companies()->firstOrFail();
    $tenantGroupId = (int) $company->tenant_group_id;

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    TenantGroup::query()->whereKey($tenantGroupId)->firstOrFail()->makeCurrent();

    Company::factory()->create([
        'tenant_group_id' => $tenantGroupId,
        'name' => 'AAA Alpha',
        'email' => 'aaa@example.com',
    ]);
    Company::factory()->create([
        'tenant_group_id' => $tenantGroupId,
        'name' => 'BBB Beta',
        'email' => 'bbb@example.com',
    ]);
    $last = Company::factory()->create([
        'tenant_group_id' => $tenantGroupId,
        'name' => 'Zzz Last',
        'email' => 'last@example.com',
    ]);

    $respSearch = $this
        ->actingAs($user)
        ->withSession([
            'current_company_id' => (int) $company->id,
            'current_tenant_group_id' => $tenantGroupId,
        ])
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
        ->withSession([
            'current_company_id' => (int) $company->id,
            'current_tenant_group_id' => $tenantGroupId,
        ])
        ->getJson(route('companies.fetch', [
            'page' => 1,
            'per_page' => 10,
            'order' => 'desc',
        ]));

    $resp->assertOk();
    expect($resp->json('data.0.id'))->toBe($last->id);
});
