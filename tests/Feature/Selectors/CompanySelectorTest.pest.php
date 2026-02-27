<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\TenantGroup;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('returns only companies linked through company_user in current tenant', function (): void {
    $tenantOne = TenantGroup::factory()->create();
    $tenantTwo = TenantGroup::factory()->create();

    $companyA = Company::factory()->create(['tenant_group_id' => $tenantOne->id, 'name' => 'A Company', 'active' => true]);
    $companyB = Company::factory()->create(['tenant_group_id' => $tenantOne->id, 'name' => 'B Company', 'active' => true]);
    $companyC = Company::factory()->create(['tenant_group_id' => $tenantTwo->id, 'name' => 'C Company', 'active' => true]);

    $user = $this->createAdminUser($companyA);
    $user->companies()->sync([$companyA->id]);

    $tenantOne->makeCurrent();

    $response = $this->actingAs($user)->withSession([
        'current_company_id' => $companyA->id,
        'current_tenant_group_id' => $tenantOne->id,
    ])->getJson(route('selectors.companies'));

    $response->assertOk();
    $ids = array_map('intval', array_column($response->json(), 'id'));
    sort($ids);

    expect($ids)->toBe([$companyA->id]);
});

it('returns both tenant companies after adding a direct company_user mapping', function (): void {
    $tenantOne = TenantGroup::factory()->create();
    $companyA = Company::factory()->create(['tenant_group_id' => $tenantOne->id, 'name' => 'A Company', 'active' => true]);
    $companyB = Company::factory()->create(['tenant_group_id' => $tenantOne->id, 'name' => 'B Company', 'active' => true]);

    $user = $this->createAdminUser($companyA);
    $user->companies()->sync([$companyA->id]);

    $tenantOne->makeCurrent();
    $session = [
        'current_company_id' => $companyA->id,
        'current_tenant_group_id' => $tenantOne->id,
    ];

    $first = $this->actingAs($user)->withSession($session)->getJson(route('selectors.companies'));
    $first->assertOk();
    expect(array_map('intval', array_column($first->json(), 'id')))->toBe([$companyA->id]);

    CompanyUser::query()->firstOrCreate([
        'company_id' => (int) $companyB->id,
        'user_id' => (int) $user->id,
    ]);

    $second = $this->actingAs($user)->withSession($session)->getJson(route('selectors.companies'));
    $second->assertOk();

    $ids = array_map('intval', array_column($second->json(), 'id'));
    sort($ids);
    expect($ids)->toBe([$companyA->id, $companyB->id]);
});

it('superadmin sees all active companies in current tenant', function (): void {
    $tenantOne = TenantGroup::factory()->create();
    $tenantTwo = TenantGroup::factory()->create();

    $companyA = Company::factory()->create(['tenant_group_id' => $tenantOne->id, 'active' => true]);
    $companyB = Company::factory()->create(['tenant_group_id' => $tenantOne->id, 'active' => true]);
    $companyC = Company::factory()->create(['tenant_group_id' => $tenantTwo->id, 'active' => true]);

    $superadmin = $this->createSuperadminUser();
    $tenantOne->makeCurrent();

    $response = $this->actingAs($superadmin)->withSession([
        'current_company_id' => $companyA->id,
        'current_tenant_group_id' => $tenantOne->id,
    ])->getJson(route('selectors.companies'));

    $response->assertOk();
    $ids = array_map('intval', array_column($response->json(), 'id'));

    expect($ids)->toContain($companyA->id);
    expect($ids)->toContain($companyB->id);
    expect($ids)->not->toContain($companyC->id);
});

it('forbids company selection page when permission is missing', function (): void {
    $tenant = TenantGroup::factory()->create();
    $company = Company::factory()->create(['tenant_group_id' => $tenant->id, 'active' => true]);

    $user = $this->createAdminUser($company);
    $user->syncRoles([]);
    $user->syncPermissions([]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $tenant->makeCurrent();

    $this->actingAs($user)
        ->withSession(['current_tenant_group_id' => $tenant->id])
        ->get(route('company.select'))
        ->assertForbidden();
});

it('does not leak unrelated tenant companies when tenant context is missing', function (): void {
    $tenantOne = TenantGroup::factory()->create();
    $tenantTwo = TenantGroup::factory()->create();
    $company = Company::factory()->create(['tenant_group_id' => $tenantOne->id, 'active' => true]);
    $otherTenantCompany = Company::factory()->create(['tenant_group_id' => $tenantTwo->id, 'active' => true]);
    $user = $this->createAdminUser($company);

    $response = $this->actingAs($user)->withSession([
        'current_company_id' => $company->id,
        'current_tenant_group_id' => null,
    ])->getJson(route('selectors.companies'));

    $response->assertOk();
    $ids = array_map('intval', array_column($response->json(), 'id'));

    expect($ids)->toContain($company->id);
    expect($ids)->not->toContain($otherTenantCompany->id);
});
