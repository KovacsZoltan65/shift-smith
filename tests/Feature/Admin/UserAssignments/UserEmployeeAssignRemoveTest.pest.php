<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\CompanyEmployee;
use App\Models\Employee;
use App\Models\TenantGroup;
use App\Models\User;
use App\Models\UserEmployee;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('assigns and replaces the employee mapping per company', function (): void {
    $tenant = TenantGroup::factory()->create();
    $companyA = Company::factory()->create(['tenant_group_id' => $tenant->id, 'active' => true]);
    $employeeOne = Employee::factory()->create(['company_id' => $companyA->id, 'active' => true]);
    $employeeTwo = Employee::factory()->create(['company_id' => $companyA->id, 'active' => true]);

    CompanyEmployee::query()->updateOrCreate(['company_id' => $companyA->id, 'employee_id' => $employeeOne->id], ['active' => true]);
    CompanyEmployee::query()->updateOrCreate(['company_id' => $companyA->id, 'employee_id' => $employeeTwo->id], ['active' => true]);

    $admin = $this->createAdminUser($companyA);
    $admin->givePermissionTo(['user_assignments.viewAny', 'user_assignments.update']);

    $target = User::factory()->create();
    $target->companies()->sync([$companyA->id]);

    $this->actingAsUserInCompany($admin, $companyA)
        ->postJson(route('admin.user_assignments.employee.assign', [
            'user' => $target->id,
            'company' => $companyA->id,
        ]), [
            'employee_id' => $employeeOne->id,
        ])
        ->assertOk();

    $this->assertDatabaseHas('user_employee', [
        'user_id' => (int) $target->id,
        'company_id' => (int) $companyA->id,
        'employee_id' => (int) $employeeOne->id,
    ]);

    $this->actingAsUserInCompany($admin, $companyA)
        ->postJson(route('admin.user_assignments.employee.assign', [
            'user' => $target->id,
            'company' => $companyA->id,
        ]), [
            'employee_id' => $employeeTwo->id,
        ])
        ->assertOk();

    $this->assertDatabaseMissing('user_employee', [
        'user_id' => (int) $target->id,
        'company_id' => (int) $companyA->id,
        'employee_id' => (int) $employeeOne->id,
    ]);

    $this->assertDatabaseHas('user_employee', [
        'user_id' => (int) $target->id,
        'company_id' => (int) $companyA->id,
        'employee_id' => (int) $employeeTwo->id,
    ]);
});

it('removes the employee mapping for a company', function (): void {
    $tenant = TenantGroup::factory()->create();
    $companyA = Company::factory()->create(['tenant_group_id' => $tenant->id, 'active' => true]);
    $employee = Employee::factory()->create(['company_id' => $companyA->id, 'active' => true]);

    CompanyEmployee::query()->updateOrCreate(['company_id' => $companyA->id, 'employee_id' => $employee->id], ['active' => true]);

    $admin = $this->createAdminUser($companyA);
    $admin->givePermissionTo(['user_assignments.viewAny', 'user_assignments.update']);

    $target = User::factory()->create();
    $target->companies()->sync([$companyA->id]);
    UserEmployee::query()->create([
        'user_id' => (int) $target->id,
        'company_id' => (int) $companyA->id,
        'employee_id' => (int) $employee->id,
        'active' => true,
    ]);

    $this->actingAsUserInCompany($admin, $companyA)
        ->deleteJson(route('admin.user_assignments.employee.destroy', [
            'user' => $target->id,
            'company' => $companyA->id,
        ]))
        ->assertOk();

    $this->assertDatabaseMissing('user_employee', [
        'user_id' => (int) $target->id,
        'company_id' => (int) $companyA->id,
    ]);
});

it('rejects assigning an employee that is not linked in company_employee', function (): void {
    $tenant = TenantGroup::factory()->create();
    $companyA = Company::factory()->create(['tenant_group_id' => $tenant->id, 'active' => true]);
    $companyB = Company::factory()->create(['tenant_group_id' => $tenant->id, 'active' => true]);
    $employee = Employee::factory()->create(['company_id' => $companyB->id, 'active' => true]);

    CompanyEmployee::query()->updateOrCreate(['company_id' => $companyB->id, 'employee_id' => $employee->id], ['active' => true]);

    $admin = $this->createAdminUser($companyA);
    $admin->givePermissionTo(['user_assignments.viewAny', 'user_assignments.update']);

    $target = User::factory()->create();
    $target->companies()->sync([$companyA->id]);

    $this->actingAsUserInCompany($admin, $companyA)
        ->postJson(route('admin.user_assignments.employee.assign', [
            'user' => $target->id,
            'company' => $companyA->id,
        ]), [
            'employee_id' => $employee->id,
        ])
        ->assertStatus(422);
});

it('enforces tenant isolation when assigning employees', function (): void {
    $tenantOne = TenantGroup::factory()->create();
    $tenantTwo = TenantGroup::factory()->create();

    $companyA = Company::factory()->create(['tenant_group_id' => $tenantOne->id, 'active' => true]);
    $companyB = Company::factory()->create(['tenant_group_id' => $tenantTwo->id, 'active' => true]);
    $employee = Employee::factory()->create(['company_id' => $companyB->id, 'active' => true]);

    CompanyEmployee::query()->updateOrCreate(['company_id' => $companyB->id, 'employee_id' => $employee->id], ['active' => true]);

    $admin = $this->createAdminUser($companyA);
    $admin->givePermissionTo(['user_assignments.viewAny', 'user_assignments.update']);

    $target = User::factory()->create();
    $target->companies()->sync([$companyA->id]);

    $this->actingAsUserInCompany($admin, $companyA)
        ->postJson(route('admin.user_assignments.employee.assign', [
            'user' => $target->id,
            'company' => $companyA->id,
        ]), [
            'employee_id' => $employee->id,
        ])
        ->assertStatus(422);
});

it('returns read only superadmin payload and blocks mapping mutations', function (): void {
    $tenant = TenantGroup::factory()->create();
    $companyA = Company::factory()->create(['tenant_group_id' => $tenant->id, 'active' => true]);
    $companyB = Company::factory()->create(['tenant_group_id' => $tenant->id, 'active' => true]);
    $employee = Employee::factory()->create(['company_id' => $companyA->id, 'active' => true]);

    CompanyEmployee::query()->updateOrCreate(['company_id' => $companyA->id, 'employee_id' => $employee->id], ['active' => true]);

    $admin = $this->createAdminUser($companyA);
    $admin->givePermissionTo(['user_assignments.viewAny', 'user_assignments.update']);

    $target = $this->createSuperadminUser();

    $this->actingAsUserInCompany($admin, $companyA)
        ->getJson(route('admin.user_assignments.fetch', ['user' => $target->id]))
        ->assertOk()
        ->assertJsonPath('data.is_superadmin', true)
        ->assertJsonPath('data.read_only', true);

    $this->actingAsUserInCompany($admin, $companyA)
        ->postJson(route('admin.user_assignments.companies.store', ['user' => $target->id]), [
            'company_id' => $companyB->id,
        ])
        ->assertStatus(422);

    $this->actingAsUserInCompany($admin, $companyA)
        ->postJson(route('admin.user_assignments.employee.assign', [
            'user' => $target->id,
            'company' => $companyA->id,
        ]), [
            'employee_id' => $employee->id,
        ])
        ->assertStatus(422);
});
