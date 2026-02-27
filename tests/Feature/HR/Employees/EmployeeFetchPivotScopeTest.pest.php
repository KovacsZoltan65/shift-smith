<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\CompanyEmployee;
use App\Models\Employee;
use App\Models\TenantGroup;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
    TenantGroup::forgetCurrent();
});

afterEach(function (): void {
    TenantGroup::forgetCurrent();
});

it('employees fetch returns zero when selected company has no pivot membership even if employee exists', function (): void {
    $tenantOne = TenantGroup::factory()->create();
    $companyA = Company::factory()->create(['tenant_group_id' => $tenantOne->id]);
    $companyB = Company::factory()->create(['tenant_group_id' => $tenantOne->id]);

    $e1 = Employee::factory()->create([
        'company_id' => $companyA->id,
        'first_name' => 'Pivot',
        'last_name' => 'OnlyA',
    ]);

    CompanyEmployee::query()->updateOrCreate(
        ['company_id' => $companyA->id, 'employee_id' => $e1->id],
        ['active' => true]
    );

    $superadmin = $this->createSuperadminUser();
    $tenantOne->makeCurrent();

    $response = $this->actingAs($superadmin)->withSession([
        'current_company_id' => $companyB->id,
        'current_tenant_group_id' => $tenantOne->id,
    ])->getJson(route('employees.fetch'));

    $response->assertOk();
    expect((int) $response->json('meta.total'))->toBe(0);
    expect(Employee::query()->count())->toBeGreaterThanOrEqual(1);
});

it('employees fetch returns one when selected company has pivot membership', function (): void {
    $tenantOne = TenantGroup::factory()->create();
    $companyA = Company::factory()->create(['tenant_group_id' => $tenantOne->id]);
    $companyB = Company::factory()->create(['tenant_group_id' => $tenantOne->id]);

    $e1 = Employee::factory()->create([
        'company_id' => $companyA->id,
        'first_name' => 'Visible',
        'last_name' => 'InA',
    ]);

    CompanyEmployee::query()->updateOrCreate(
        ['company_id' => $companyA->id, 'employee_id' => $e1->id],
        ['active' => true]
    );

    // Explicitly ensure there is no B membership.
    CompanyEmployee::query()
        ->where('company_id', $companyB->id)
        ->where('employee_id', $e1->id)
        ->delete();

    $superadmin = $this->createSuperadminUser();
    $tenantOne->makeCurrent();

    $response = $this->actingAs($superadmin)->withSession([
        'current_company_id' => $companyA->id,
        'current_tenant_group_id' => $tenantOne->id,
    ])->getJson(route('employees.fetch'));

    $response->assertOk();
    expect((int) $response->json('meta.total'))->toBe(1);
    expect(array_map('intval', array_column($response->json('data') ?? [], 'id')))->toContain((int) $e1->id);
});

it('employees fetch does not leak tenant-foreign rows', function (): void {
    $tenantOne = TenantGroup::factory()->create();
    $tenantTwo = TenantGroup::factory()->create();

    $companyA = Company::factory()->create(['tenant_group_id' => $tenantOne->id]);
    $companyC = Company::factory()->create(['tenant_group_id' => $tenantTwo->id]);

    $eTenantOne = Employee::factory()->create(['company_id' => $companyA->id]);
    $eTenantTwo = Employee::factory()->create(['company_id' => $companyC->id]);

    CompanyEmployee::query()->updateOrCreate(
        ['company_id' => $companyA->id, 'employee_id' => $eTenantOne->id],
        ['active' => true]
    );
    CompanyEmployee::query()->updateOrCreate(
        ['company_id' => $companyC->id, 'employee_id' => $eTenantTwo->id],
        ['active' => true]
    );

    $superadmin = $this->createSuperadminUser();
    $tenantOne->makeCurrent();

    $response = $this->actingAs($superadmin)->withSession([
        'current_company_id' => $companyA->id,
        'current_tenant_group_id' => $tenantOne->id,
    ])->getJson(route('employees.fetch'));

    $response->assertOk();
    $ids = array_map('intval', array_column($response->json('data') ?? [], 'id'));

    expect($ids)->toContain((int) $eTenantOne->id);
    expect($ids)->not->toContain((int) $eTenantTwo->id);
});

