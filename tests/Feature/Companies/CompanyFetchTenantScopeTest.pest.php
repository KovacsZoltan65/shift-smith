<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\TenantGroup;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
    TenantGroup::forgetCurrent();
});

afterEach(function (): void {
    TenantGroup::forgetCurrent();
});

it('fetch returns only current tenant companies', function (): void {
    $tenantOne = TenantGroup::factory()->create();
    $tenantTwo = TenantGroup::factory()->create();

    $companyOne = Company::factory()->create([
        'tenant_group_id' => $tenantOne->id,
        'name' => 'Tenant One Company',
    ]);
    $companyTwo = Company::factory()->create([
        'tenant_group_id' => $tenantTwo->id,
        'name' => 'Tenant Two Company',
    ]);

    $user = $this->createAdminUser($companyOne);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $response = $this->actingAs($user)
        ->withSession(['current_tenant_group_id' => $tenantOne->id])
        ->getJson(route('companies.fetch', [
            'page' => 1,
            'per_page' => 50,
            'order' => 'desc',
        ]));

    $response->assertOk();

    $ids = array_map('intval', array_column($response->json('data'), 'id'));
    expect($ids)->toContain($companyOne->id);
    expect($ids)->not->toContain($companyTwo->id);
});

it('fetch bootstraps the single selectable company tenant for non superadmin', function (): void {
    $tenant = TenantGroup::factory()->create();
    $company = Company::factory()->create([
        'tenant_group_id' => $tenant->id,
        'name' => 'Bootstrapped Tenant Company',
    ]);

    $user = $this->createAdminUser($company);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $response = $this->actingAs($user)
        ->withSession(['current_tenant_group_id' => null])
        ->getJson(route('companies.fetch', [
            'page' => 1,
            'per_page' => 50,
            'order' => 'desc',
        ]));

    $response
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $company->id);
});

it('by-name lookup does not leak companies across tenant groups', function (): void {
    $tenantOne = TenantGroup::factory()->create();
    $tenantTwo = TenantGroup::factory()->create();

    $companyOne = Company::factory()->create([
        'tenant_group_id' => $tenantOne->id,
        'name' => 'Tenant Scoped Name',
    ]);

    Company::factory()->create([
        'tenant_group_id' => $tenantTwo->id,
        'name' => 'Tenant Scoped Name',
    ]);

    $user = $this->createAdminUser($companyOne);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $this->actingAs($user)
        ->withSession(['current_tenant_group_id' => $tenantOne->id])
        ->getJson(route('companies.by_name', ['name' => 'Tenant Scoped Name']))
        ->assertOk()
        ->assertJsonPath('data.id', $companyOne->id);
});
