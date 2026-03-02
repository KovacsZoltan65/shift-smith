<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\CompanyEmployee;
use App\Models\Employee;
use App\Models\TenantGroup;
use App\Models\User;
use App\Services\UserAssignmentService;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('replaces the employee assignment for the same user and company', function (): void {
    $tenant = TenantGroup::factory()->create();
    $tenant->makeCurrent();

    $company = Company::factory()->create([
        'tenant_group_id' => (int) $tenant->id,
        'active' => true,
    ]);

    $employeeOne = Employee::factory()->create([
        'company_id' => (int) $company->id,
        'active' => true,
    ]);
    $employeeTwo = Employee::factory()->create([
        'company_id' => (int) $company->id,
        'active' => true,
    ]);

    CompanyEmployee::query()->updateOrCreate(
        [
            'company_id' => (int) $company->id,
            'employee_id' => (int) $employeeOne->id,
        ],
        ['active' => true]
    );
    CompanyEmployee::query()->updateOrCreate(
        [
            'company_id' => (int) $company->id,
            'employee_id' => (int) $employeeTwo->id,
        ],
        ['active' => true]
    );

    $actor = $this->createAdminUser($company);
    $target = User::factory()->create();
    $target->companies()->syncWithoutDetaching([(int) $company->id]);

    /** @var UserAssignmentService $service */
    $service = app(UserAssignmentService::class);
    $service->assignEmployee($actor, $target, $company, $employeeOne);
    $service->assignEmployee($actor, $target, $company, $employeeTwo);

    $this->assertDatabaseMissing('user_employee', [
        'user_id' => (int) $target->id,
        'company_id' => (int) $company->id,
        'employee_id' => (int) $employeeOne->id,
    ]);

    $this->assertDatabaseHas('user_employee', [
        'user_id' => (int) $target->id,
        'company_id' => (int) $company->id,
        'employee_id' => (int) $employeeTwo->id,
    ]);
});
