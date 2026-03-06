<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Employee;
use App\Models\Org\EmployeeSupervisor;
use App\Models\TenantGroup;
use App\Models\User;
use App\Services\EmployeeSupervisorService;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('returns 403 for hierarchy employee search without permission', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $tenant->makeCurrent();

    /** @var User $user */
    $user = User::factory()->create();
    $user->assignRole('user');
    $user->companies()->syncWithoutDetaching([$company->id]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $this->actingAsUserInCompany($user, $company)
        ->getJson(route('org.hierarchy.employees.search', ['company_id' => $company->id, 'q' => 'ann']))
        ->assertForbidden();
});

it('returns company-scoped hierarchy employee search results for authorized user', function (): void {
    $tenant = TenantGroup::factory()->create();
    $companyA = Company::factory()->create(['tenant_group_id' => $tenant->id]);
    $companyB = Company::factory()->create(['tenant_group_id' => $tenant->id]);
    $tenant->makeCurrent();

    $admin = $this->createAdminUser($companyA);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $admin->refresh();

    $inScope = Employee::factory()->create([
        'company_id' => $companyA->id,
        'first_name' => 'Anna',
        'last_name' => 'Kiss',
        'email' => 'anna.a@example.test',
    ]);
    Employee::factory()->create([
        'company_id' => $companyB->id,
        'first_name' => 'Anna',
        'last_name' => 'Nagy',
        'email' => 'anna.b@example.test',
    ]);

    $response = $this->actingAsUserInCompany($admin, $companyA)
        ->getJson(route('org.hierarchy.employees.search', [
            'company_id' => $companyA->id,
            'q' => 'anna',
            'limit' => 10,
        ]))
        ->assertOk();

    $ids = collect($response->json('data'))->pluck('id')->map(fn ($id): int => (int) $id)->all();
    expect($ids)->toContain((int) $inScope->id);
    expect($ids)->toHaveCount(1);
});

it('enforces company scope for hierarchy employee search endpoint', function (): void {
    $tenant = TenantGroup::factory()->create();
    $companyA = Company::factory()->create(['tenant_group_id' => $tenant->id]);
    $companyB = Company::factory()->create(['tenant_group_id' => $tenant->id]);
    $tenant->makeCurrent();

    $admin = $this->createAdminUser($companyA);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $admin->refresh();

    $this->actingAsUserInCompany($admin, $companyA)
        ->getJson(route('org.hierarchy.employees.search', ['company_id' => $companyB->id, 'q' => 'a']))
        ->assertForbidden();
});

it('returns 403 for hierarchy path without permission', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $tenant->makeCurrent();

    $employee = Employee::factory()->create(['company_id' => $company->id]);

    /** @var User $user */
    $user = User::factory()->create();
    $user->assignRole('user');
    $user->companies()->syncWithoutDetaching([$company->id]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $this->actingAsUserInCompany($user, $company)
        ->getJson(route('org.hierarchy.path', ['company_id' => $company->id, 'employee_id' => $employee->id]))
        ->assertForbidden();
});

it('returns hierarchy path in CEO to employee order', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $tenant->makeCurrent();

    $admin = $this->createAdminUser($company);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $admin->refresh();

    $ceo = Employee::factory()->create([
        'company_id' => $company->id,
        'org_level' => Employee::ORG_LEVEL_CEO,
    ]);
    $manager = Employee::factory()->create([
        'company_id' => $company->id,
        'org_level' => Employee::ORG_LEVEL_MANAGER,
    ]);
    $staff = Employee::factory()->create([
        'company_id' => $company->id,
        'org_level' => Employee::ORG_LEVEL_STAFF,
    ]);

    $service = app(EmployeeSupervisorService::class);
    $service->assignSupervisor(
        companyId: (int) $company->id,
        employeeId: (int) $manager->id,
        supervisorEmployeeId: (int) $ceo->id,
        validFrom: now()->subDays(20)->toDateString(),
        actorUserId: (int) $admin->id
    );
    $service->assignSupervisor(
        companyId: (int) $company->id,
        employeeId: (int) $staff->id,
        supervisorEmployeeId: (int) $manager->id,
        validFrom: now()->subDays(10)->toDateString(),
        actorUserId: (int) $admin->id
    );

    $response = $this->actingAsUserInCompany($admin, $company)
        ->getJson(route('org.hierarchy.path', [
            'company_id' => $company->id,
            'employee_id' => $staff->id,
            'at_date' => now()->toDateString(),
        ]))
        ->assertOk();

    $ids = collect($response->json('data'))->pluck('id')->map(fn ($id): int => (int) $id)->all();
    expect($ids)->toBe([(int) $ceo->id, (int) $manager->id, (int) $staff->id]);
});

it('returns 422 for hierarchy path cycle guard violations', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $tenant->makeCurrent();

    $admin = $this->createAdminUser($company);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $admin->refresh();

    $employeeA = Employee::factory()->create(['company_id' => $company->id]);
    $employeeB = Employee::factory()->create(['company_id' => $company->id]);

    EmployeeSupervisor::query()->create([
        'company_id' => $company->id,
        'employee_id' => $employeeA->id,
        'supervisor_employee_id' => $employeeB->id,
        'valid_from' => now()->subDays(5)->toDateString(),
        'valid_to' => null,
        'created_by_user_id' => $admin->id,
    ]);
    EmployeeSupervisor::query()->create([
        'company_id' => $company->id,
        'employee_id' => $employeeB->id,
        'supervisor_employee_id' => $employeeA->id,
        'valid_from' => now()->subDays(5)->toDateString(),
        'valid_to' => null,
        'created_by_user_id' => $admin->id,
    ]);

    $this->actingAsUserInCompany($admin, $company)
        ->getJson(route('org.hierarchy.path', [
            'company_id' => $company->id,
            'employee_id' => $employeeA->id,
            'at_date' => now()->toDateString(),
        ]))
        ->assertUnprocessable();
});
