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

it('employee fetch returns only employees mapped to selected company through pivot', function (): void {
    $tenant = TenantGroup::factory()->create();
    $companyA = Company::factory()->create(['tenant_group_id' => $tenant->id]);
    $companyB = Company::factory()->create(['tenant_group_id' => $tenant->id]);

    $employeeA = Employee::factory()->create(['company_id' => $companyA->id, 'first_name' => 'Alice']);
    $employeeB = Employee::factory()->create(['company_id' => $companyA->id, 'first_name' => 'Bob']);

    // Keep employeeA in A, move employeeB to B from access perspective.
    CompanyEmployee::query()->where('employee_id', $employeeB->id)->where('company_id', $companyA->id)->delete();
    CompanyEmployee::query()->updateOrCreate(
        ['company_id' => $companyB->id, 'employee_id' => $employeeB->id],
        ['active' => true]
    );

    $user = $this->createAdminUser($companyA);
    UserEmployee::query()->where('user_id', $user->id)->delete();
    UserEmployee::query()->updateOrCreate(
        ['user_id' => $user->id, 'company_id' => $companyA->id],
        ['employee_id' => $employeeA->id, 'active' => true]
    );

    $tenant->makeCurrent();

    $response = $this->actingAs($user)->withSession([
        'current_company_id' => $companyA->id,
        'current_tenant_group_id' => $tenant->id,
    ])->getJson(route('employees.fetch'));

    $response->assertOk();
    $ids = array_map('intval', array_column($response->json('data') ?? [], 'id'));

    expect($ids)->toContain((int) $employeeA->id);
    expect($ids)->not->toContain((int) $employeeB->id);
});

it('employee read is forbidden when permission is missing even with pivot relation', function (): void {
    $tenant = TenantGroup::factory()->create();
    $company = Company::factory()->create(['tenant_group_id' => $tenant->id]);
    $employee = Employee::factory()->create(['company_id' => $company->id]);
    CompanyEmployee::query()->updateOrCreate(
        ['company_id' => $company->id, 'employee_id' => $employee->id],
        ['active' => true]
    );

    $user = $this->createAdminUser($company);
    UserEmployee::query()->where('user_id', $user->id)->delete();
    UserEmployee::query()->updateOrCreate(
        ['user_id' => $user->id, 'company_id' => $company->id],
        ['employee_id' => $employee->id, 'active' => true]
    );

    $user->syncRoles([]);
    $user->syncPermissions([]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $tenant->makeCurrent();

    $this->actingAs($user)->withSession([
        'current_company_id' => $company->id,
        'current_tenant_group_id' => $tenant->id,
    ])->getJson(route('employees.by_id', ['id' => $employee->id]))
        ->assertStatus(302);
});

it('employee read is forbidden without pivot relation even when permission exists', function (): void {
    $tenant = TenantGroup::factory()->create();
    $companyA = Company::factory()->create(['tenant_group_id' => $tenant->id]);
    $companyB = Company::factory()->create(['tenant_group_id' => $tenant->id]);
    $employeeA = Employee::factory()->create(['company_id' => $companyA->id]);
    $employeeB = Employee::factory()->create(['company_id' => $companyB->id]);

    CompanyEmployee::query()->updateOrCreate(
        ['company_id' => $companyA->id, 'employee_id' => $employeeA->id],
        ['active' => true]
    );
    CompanyEmployee::query()->updateOrCreate(
        ['company_id' => $companyB->id, 'employee_id' => $employeeB->id],
        ['active' => true]
    );

    $user = $this->createAdminUser($companyA);
    UserEmployee::query()->where('user_id', $user->id)->delete();
    UserEmployee::query()->updateOrCreate(
        ['user_id' => $user->id, 'company_id' => $companyA->id],
        ['employee_id' => $employeeA->id, 'active' => true]
    );

    $tenant->makeCurrent();

    $this->actingAs($user)->withSession([
        'current_company_id' => $companyA->id,
        'current_tenant_group_id' => $tenant->id,
    ])->getJson(route('employees.by_id', ['id' => $employeeB->id]))
        ->assertForbidden();
});
