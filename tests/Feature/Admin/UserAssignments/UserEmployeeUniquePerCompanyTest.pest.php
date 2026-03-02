<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\CompanyEmployee;
use App\Models\Employee;
use App\Models\TenantGroup;
use App\Models\User;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('prevents assigning same employee to two users in same company', function (): void {
    $tenant = TenantGroup::factory()->create();
    $companyA = Company::factory()->create(['tenant_group_id' => $tenant->id, 'active' => true]);
    $employee = Employee::factory()->create(['company_id' => $companyA->id, 'active' => true]);

    CompanyEmployee::query()->updateOrCreate(
        ['company_id' => $companyA->id, 'employee_id' => $employee->id],
        ['active' => true]
    );

    $admin = $this->createAdminUser($companyA);
    $admin->givePermissionTo(['user_assignments.viewAny', 'user_assignments.update']);

    $userOne = User::factory()->create();
    $userTwo = User::factory()->create();
    $userOne->companies()->sync([$companyA->id]);
    $userTwo->companies()->sync([$companyA->id]);

    $this->actingAsUserInCompany($admin, $companyA)
        ->postJson(route('admin.user_assignments.employee.assign', [
            'user' => $userOne->id,
            'company' => $companyA->id,
        ]), [
            'employee_id' => $employee->id,
        ])
        ->assertOk();

    $this->actingAsUserInCompany($admin, $companyA)
        ->postJson(route('admin.user_assignments.employee.assign', [
            'user' => $userTwo->id,
            'company' => $companyA->id,
        ]), [
            'employee_id' => $employee->id,
        ])
        ->assertStatus(422)
        ->assertJsonPath(
            'errors.employee_id.0',
            'Ez a dolgozó már hozzá van rendelve egy másik felhasználóhoz ebben a cégben.',
        );
});

it('allows same employee in different company when company employee link exists in both companies', function (): void {
    $tenant = TenantGroup::factory()->create();
    $companyA = Company::factory()->create(['tenant_group_id' => $tenant->id, 'active' => true]);
    $companyB = Company::factory()->create(['tenant_group_id' => $tenant->id, 'active' => true]);
    $employee = Employee::factory()->create(['company_id' => $companyA->id, 'active' => true]);

    CompanyEmployee::query()->updateOrCreate(
        ['company_id' => $companyA->id, 'employee_id' => $employee->id],
        ['active' => true]
    );
    CompanyEmployee::query()->updateOrCreate(
        ['company_id' => $companyB->id, 'employee_id' => $employee->id],
        ['active' => true]
    );

    $admin = $this->createAdminUser($companyA);
    $admin->givePermissionTo(['user_assignments.viewAny', 'user_assignments.update']);

    $userOne = User::factory()->create();
    $userTwo = User::factory()->create();
    $userOne->companies()->sync([$companyA->id]);
    $userTwo->companies()->sync([$companyB->id]);

    $this->actingAsUserInCompany($admin, $companyA)
        ->postJson(route('admin.user_assignments.employee.assign', [
            'user' => $userOne->id,
            'company' => $companyA->id,
        ]), [
            'employee_id' => $employee->id,
        ])
        ->assertOk();

    $this->actingAsUserInCompany($admin, $companyA)
        ->postJson(route('admin.user_assignments.employee.assign', [
            'user' => $userTwo->id,
            'company' => $companyB->id,
        ]), [
            'employee_id' => $employee->id,
        ])
        ->assertOk();
});
