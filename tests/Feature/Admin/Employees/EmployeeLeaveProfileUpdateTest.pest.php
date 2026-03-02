<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Employee;
use App\Models\TenantGroup;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('returns 403 without employee update permission for leave profile update', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $employee = Employee::factory()->create(['company_id' => $company->id]);
    $user = $this->createAdminUser($company);

    $user->syncRoles([]);
    $user->syncPermissions([]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();
    $tenant->makeCurrent();

    $this->actingAs($user)->withSession([
        'current_company_id' => (int) $company->id,
        'current_tenant_group_id' => (int) $tenant->id,
    ])->putJson(route('employees.leave_profile.update', ['id' => $employee->id]), [
        'birth_date' => '1990-01-01',
        'children_count' => 1,
        'disabled_children_count' => 0,
        'is_disabled' => false,
    ])->assertStatus(302);
});

it('validates leave profile payload', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $employee = Employee::factory()->create(['company_id' => $company->id]);
    $user = $this->createAdminUser($company);

    $tenant->makeCurrent();

    $this->actingAsUserInCompany($user, $company)
        ->putJson(route('employees.leave_profile.update', ['id' => $employee->id]), [
            'birth_date' => 'invalid-date',
            'children_count' => -1,
            'disabled_children_count' => 21,
            'is_disabled' => 'nope',
        ])->assertStatus(422)
        ->assertJsonValidationErrors([
            'birth_date',
            'children_count',
            'disabled_children_count',
            'is_disabled',
        ]);
});

it('enforces current company scope on leave profile update', function (): void {
    $tenant = TenantGroup::factory()->create();
    $companyA = Company::factory()->create(['tenant_group_id' => $tenant->id]);
    $companyB = Company::factory()->create(['tenant_group_id' => $tenant->id]);
    $employee = Employee::factory()->create(['company_id' => $companyB->id]);
    $user = $this->createAdminUser($companyA);
    $user->companies()->syncWithoutDetaching([$companyB->id]);

    $tenant->makeCurrent();

    $this->actingAs($user)->withSession([
        'current_company_id' => (int) $companyA->id,
        'current_tenant_group_id' => (int) $tenant->id,
    ])->putJson(route('employees.leave_profile.update', ['id' => $employee->id]), [
        'birth_date' => '1990-01-01',
        'children_count' => 2,
        'disabled_children_count' => 0,
        'is_disabled' => false,
    ])->assertNotFound();
});

it('upserts the leave profile on the employee record', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $employee = Employee::factory()->create([
        'company_id' => $company->id,
        'birth_date' => null,
        'children_count' => 0,
        'disabled_children_count' => 0,
        'is_disabled' => false,
    ]);
    $user = $this->createAdminUser($company);

    $tenant->makeCurrent();

    $this->actingAsUserInCompany($user, $company)
        ->putJson(route('employees.leave_profile.update', ['id' => $employee->id]), [
            'birth_date' => '1992-03-04',
            'children_count' => 3,
            'disabled_children_count' => 1,
            'is_disabled' => true,
        ])->assertOk()
        ->assertJsonPath('data.birth_date', '1992-03-04')
        ->assertJsonPath('data.children_count', 3)
        ->assertJsonPath('data.disabled_children_count', 1)
        ->assertJsonPath('data.is_disabled', true);

    $this->assertDatabaseHas('employees', [
        'id' => $employee->id,
        'company_id' => $company->id,
        'birth_date' => '1992-03-04',
        'children_count' => 3,
        'disabled_children_count' => 1,
        'is_disabled' => 1,
    ]);
});
