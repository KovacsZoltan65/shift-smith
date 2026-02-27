<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\CompanyEmployee;
use App\Models\Employee;
use App\Models\TenantGroup;
use App\Models\UserEmployee;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('returns only companies linked through user_employee -> company_employee in current tenant', function (): void {
    $tenantOne = TenantGroup::factory()->create();
    $tenantTwo = TenantGroup::factory()->create();

    $companyA = Company::factory()->create(['tenant_group_id' => $tenantOne->id, 'name' => 'A Company', 'active' => true]);
    $companyB = Company::factory()->create(['tenant_group_id' => $tenantOne->id, 'name' => 'B Company', 'active' => true]);
    $companyC = Company::factory()->create(['tenant_group_id' => $tenantTwo->id, 'name' => 'C Company', 'active' => true]);

    $e1 = Employee::factory()->create(['company_id' => $companyA->id]);
    $e2 = Employee::factory()->create(['company_id' => $companyB->id]);
    $e3 = Employee::factory()->create(['company_id' => $companyC->id]);

    CompanyEmployee::query()->updateOrCreate(['company_id' => $companyA->id, 'employee_id' => $e1->id], ['active' => true]);
    CompanyEmployee::query()->updateOrCreate(['company_id' => $companyB->id, 'employee_id' => $e2->id], ['active' => true]);
    CompanyEmployee::query()->updateOrCreate(['company_id' => $companyC->id, 'employee_id' => $e3->id], ['active' => true]);

    $user = $this->createAdminUser($companyA);
    UserEmployee::query()->where('user_id', $user->id)->delete();
    UserEmployee::query()->updateOrCreate(['user_id' => $user->id, 'employee_id' => $e1->id], ['active' => true]);

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

it('returns both tenant companies after adding a matching company_employee mapping', function (): void {
    $tenantOne = TenantGroup::factory()->create();
    $companyA = Company::factory()->create(['tenant_group_id' => $tenantOne->id, 'name' => 'A Company', 'active' => true]);
    $companyB = Company::factory()->create(['tenant_group_id' => $tenantOne->id, 'name' => 'B Company', 'active' => true]);

    $e1 = Employee::factory()->create(['company_id' => $companyA->id]);
    $e2 = Employee::factory()->create(['company_id' => $companyB->id]);

    CompanyEmployee::query()->updateOrCreate(['company_id' => $companyA->id, 'employee_id' => $e1->id], ['active' => true]);
    CompanyEmployee::query()->updateOrCreate(['company_id' => $companyB->id, 'employee_id' => $e2->id], ['active' => true]);

    $user = $this->createAdminUser($companyA);
    UserEmployee::query()->where('user_id', $user->id)->delete();
    UserEmployee::query()->updateOrCreate(['user_id' => $user->id, 'employee_id' => $e1->id], ['active' => true]);

    $tenantOne->makeCurrent();
    $session = [
        'current_company_id' => $companyA->id,
        'current_tenant_group_id' => $tenantOne->id,
    ];

    $first = $this->actingAs($user)->withSession($session)->getJson(route('selectors.companies'));
    $first->assertOk();
    expect(array_map('intval', array_column($first->json(), 'id')))->toBe([$companyA->id]);

    CompanyEmployee::query()->updateOrCreate(['company_id' => $companyB->id, 'employee_id' => $e1->id], ['active' => true]);

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
