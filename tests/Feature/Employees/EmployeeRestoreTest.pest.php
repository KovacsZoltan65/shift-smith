<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Employee;
use App\Models\TenantGroup;
use App\Models\User;
use App\Models\UserEmployee;
use App\Repositories\EmployeeSupervisorRepositoryInterface;
use App\Services\Cache\CacheNamespaces;
use App\Services\Cache\CacheVersionService;
use App\Services\EmployeeSupervisorService;
use Carbon\CarbonImmutable;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

function restoreAdminEmployee(User $user, Company $company): Employee
{
    $employeeId = UserEmployee::query()
        ->where('user_id', $user->id)
        ->where('company_id', $company->id)
        ->value('employee_id');

    expect(is_numeric($employeeId))->toBeTrue();

    /** @var Employee $employee */
    $employee = Employee::query()->findOrFail((int) $employeeId);

    return $employee;
}

it('returns validation error when creating an active duplicate email in the same company', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $tenant->makeCurrent();
    $admin = $this->createAdminUser($company);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $admin->refresh();

    Employee::factory()->create([
        'company_id' => $company->id,
        'email' => 'duplicate@test.hu',
    ]);

    $this->actingAsUserInCompany($admin, $company)
        ->postJson(route('employees.store'), [
            'company_id' => $company->id,
            'first_name' => 'Teszt',
            'last_name' => 'Dolgozó',
            'email' => 'duplicate@test.hu',
            'birth_date' => '1990-01-01',
            'active' => true,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

it('returns restore_available instead of creating a new employee for soft-deleted duplicate email', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $tenant->makeCurrent();
    $admin = $this->createAdminUser($company);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $admin->refresh();

    $employee = Employee::factory()->create([
        'company_id' => $company->id,
        'first_name' => 'Kiss',
        'last_name' => 'Péter',
        'email' => 'restore@test.hu',
    ]);
    $employee->delete();

    $beforeCount = Employee::withTrashed()
        ->where('company_id', $company->id)
        ->count();

    $this->actingAsUserInCompany($admin, $company)
        ->postJson(route('employees.store'), [
            'company_id' => $company->id,
            'first_name' => 'Másik',
            'last_name' => 'Név',
            'email' => 'restore@test.hu',
            'birth_date' => '1990-01-01',
            'active' => true,
        ])
        ->assertStatus(409)
        ->assertJsonPath('restore_available', true)
        ->assertJsonPath('employee.id', $employee->id);

    $afterCount = Employee::withTrashed()
        ->where('company_id', $company->id)
        ->count();

    expect($afterCount)->toBe($beforeCount);
});

it('restores a soft-deleted employee and updates fields from the payload', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $tenant->makeCurrent();
    $admin = $this->createAdminUser($company);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $admin->refresh();

    $employee = Employee::factory()->create([
        'company_id' => $company->id,
        'first_name' => 'Régi',
        'last_name' => 'Dolgozó',
        'email' => 'restore-success@test.hu',
        'address' => 'Régi cím',
        'phone' => '111',
        'active' => false,
    ]);
    $employee->delete();

    $this->actingAsUserInCompany($admin, $company)
        ->postJson(route('employees.restore', $employee->id), [
            'company_id' => $company->id,
            'first_name' => 'Új',
            'last_name' => 'Név',
            'email' => 'restore-success@test.hu',
            'address' => 'Új cím',
            'phone' => '222',
            'birth_date' => '1991-02-03',
            'hired_at' => '2026-03-06',
            'active' => true,
        ])
        ->assertOk()
        ->assertJsonPath('success', true);

    $restored = Employee::query()->findOrFail($employee->id);
    expect($restored->deleted_at)->toBeNull();
    expect($restored->first_name)->toBe('Új');
    expect($restored->last_name)->toBe('Név');
    expect($restored->address)->toBe('Új cím');
    expect($restored->phone)->toBe('222');
    expect((bool) $restored->active)->toBeTrue();
});

it('enforces company scope on restore', function (): void {
    [$tenant, $companyA] = $this->createTenantWithCompany();
    $tenant->makeCurrent();
    $admin = $this->createAdminUser($companyA);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $admin->refresh();

    $companyB = Company::factory()->create(['tenant_group_id' => $tenant->id]);
    $employee = Employee::factory()->create([
        'company_id' => $companyA->id,
        'email' => 'scope@test.hu',
    ]);
    $employee->delete();

    $this->actingAsUserInCompany($admin, $companyA)
        ->postJson(route('employees.restore', $employee->id), [
            'company_id' => $companyB->id,
            'first_name' => 'Teszt',
            'last_name' => 'Dolgozó',
            'email' => 'scope@test.hu',
            'birth_date' => '1990-01-01',
            'active' => true,
        ])
        ->assertForbidden();
});

it('enforces tenant isolation on restore', function (): void {
    [$tenantA, $companyA] = $this->createTenantWithCompany();
    $tenantA->makeCurrent();
    $employee = Employee::factory()->create([
        'company_id' => $companyA->id,
        'email' => 'tenant@test.hu',
    ]);
    $employee->delete();

    $tenantB = TenantGroup::factory()->create();
    $companyB = Company::factory()->create(['tenant_group_id' => $tenantB->id]);
    $tenantB->makeCurrent();
    $adminB = $this->createAdminUser($companyB);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $adminB->refresh();

    $this->actingAsUserInCompany($adminB, $companyB)
        ->postJson(route('employees.restore', $employee->id), [
            'company_id' => $companyA->id,
            'first_name' => 'Teszt',
            'last_name' => 'Dolgozó',
            'email' => 'tenant@test.hu',
            'birth_date' => '1990-01-01',
            'active' => true,
        ])
        ->assertForbidden();
});

it('does not automatically recreate an active supervisor relation on restore', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $tenant->makeCurrent();
    $admin = $this->createAdminUser($company);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $admin->refresh();
    $adminEmployee = restoreAdminEmployee($admin, $company);

    $employee = Employee::factory()->create([
        'company_id' => $company->id,
        'email' => 'hierarchy@test.hu',
    ]);

    app(EmployeeSupervisorService::class)->assignSupervisor(
        (int) $company->id,
        (int) $employee->id,
        (int) $adminEmployee->id,
        '2026-01-01',
        (int) $admin->id,
    );

    $this->actingAsUserInCompany($admin, $company)
        ->deleteJson(route('employees.destroy', $employee->id), [
            'company_id' => $company->id,
            'effective_from' => '2026-03-06',
            'strategy' => 'none',
        ])
        ->assertOk();

    $this->actingAsUserInCompany($admin, $company)
        ->postJson(route('employees.restore', $employee->id), [
            'company_id' => $company->id,
            'first_name' => 'Teszt',
            'last_name' => 'Dolgozó',
            'email' => 'hierarchy@test.hu',
            'birth_date' => '1990-01-01',
            'active' => true,
        ])
        ->assertOk();

    $activeRelation = app(EmployeeSupervisorRepositoryInterface::class)
        ->findActiveSupervisor((int) $company->id, (int) $employee->id, CarbonImmutable::parse('2026-03-10'));

    expect($activeRelation)->toBeNull();
});

it('keeps supervisor history unchanged during restore', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $tenant->makeCurrent();
    $admin = $this->createAdminUser($company);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $admin->refresh();
    $adminEmployee = restoreAdminEmployee($admin, $company);

    $employee = Employee::factory()->create([
        'company_id' => $company->id,
        'email' => 'history@test.hu',
    ]);

    app(EmployeeSupervisorService::class)->assignSupervisor(
        (int) $company->id,
        (int) $employee->id,
        (int) $adminEmployee->id,
        '2026-01-01',
        (int) $admin->id,
    );

    $this->actingAsUserInCompany($admin, $company)
        ->deleteJson(route('employees.destroy', $employee->id), [
            'company_id' => $company->id,
            'effective_from' => '2026-03-06',
            'strategy' => 'none',
        ])
        ->assertOk();

    $historyBefore = app(EmployeeSupervisorRepositoryInterface::class)
        ->listSupervisorHistory((int) $company->id, (int) $employee->id);

    $this->actingAsUserInCompany($admin, $company)
        ->postJson(route('employees.restore', $employee->id), [
            'company_id' => $company->id,
            'first_name' => 'Teszt',
            'last_name' => 'Dolgozó',
            'email' => 'history@test.hu',
            'birth_date' => '1990-01-01',
            'active' => true,
        ])
        ->assertOk();

    $historyAfter = app(EmployeeSupervisorRepositoryInterface::class)
        ->listSupervisorHistory((int) $company->id, (int) $employee->id);

    expect($historyAfter)->toHaveCount(count($historyBefore));
    expect($historyAfter[0]->valid_to?->format('Y-m-d'))->toBe($historyBefore[0]->valid_to?->format('Y-m-d'));
});

it('bumps employee and org cache versions after restore', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $tenant->makeCurrent();
    $admin = $this->createAdminUser($company);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $admin->refresh();

    $employee = Employee::factory()->create([
        'company_id' => $company->id,
        'email' => 'cache@test.hu',
    ]);
    $employee->delete();

    $versioner = app(CacheVersionService::class);
    $employeesNamespace = CacheNamespaces::tenantEmployees((int) $tenant->id, (int) $company->id);
    $orgNamespace = CacheNamespaces::tenantOrgHierarchy((int) $tenant->id, (int) $company->id);
    $employeesBefore = $versioner->get("{$employeesNamespace}:index");
    $employeeSelectorBefore = $versioner->get("{$employeesNamespace}:selector");
    $orgBefore = $versioner->get("{$orgNamespace}:hierarchy");
    $pathBefore = $versioner->get("{$orgNamespace}:path");

    $this->actingAsUserInCompany($admin, $company)
        ->postJson(route('employees.restore', $employee->id), [
            'company_id' => $company->id,
            'first_name' => 'Teszt',
            'last_name' => 'Dolgozó',
            'email' => 'cache@test.hu',
            'birth_date' => '1990-01-01',
            'active' => true,
        ])
        ->assertOk();

    expect($versioner->get("{$employeesNamespace}:index"))->toBeGreaterThan($employeesBefore);
    expect($versioner->get("{$employeesNamespace}:selector"))->toBeGreaterThan($employeeSelectorBefore);
    expect($versioner->get("{$orgNamespace}:hierarchy"))->toBeGreaterThan($orgBefore);
    expect($versioner->get("{$orgNamespace}:path"))->toBeGreaterThan($pathBefore);
});
