<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Admin\Role;
use App\Models\CompanyEmployee;
use App\Models\Employee;
use App\Models\TenantGroup;
use App\Models\User;
use App\Services\Cache\CacheVersionService;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('attaches and detaches employee mapping for a user in current tenant', function (): void {
    $tenantOne = TenantGroup::factory()->create();
    $companyA = Company::factory()->create(['tenant_group_id' => $tenantOne->id, 'active' => true]);

    $e1 = Employee::factory()->create([
        'company_id' => $companyA->id,
        'active' => true,
    ]);

    $e2 = Employee::factory()->create([
        'company_id' => $companyA->id,
        'active' => true,
    ]);

    CompanyEmployee::query()->updateOrCreate(
        ['company_id' => $companyA->id, 'employee_id' => $e1->id],
        ['active' => true]
    );
    CompanyEmployee::query()->updateOrCreate(
        ['company_id' => $companyA->id, 'employee_id' => $e2->id],
        ['active' => true]
    );

    $adminUser = $this->createAdminUser($companyA);
    $adminUser->givePermissionTo([
        'user_employees.viewAny',
        'user_employees.create',
        'user_employees.delete',
    ]);

    $targetUser = User::factory()->create();
    $targetUser->companies()->syncWithoutDetaching([$companyA->id]);

    $this->actingAsUserInCompany($adminUser, $companyA)
        ->postJson(route('admin.user_employees.store', ['user' => $targetUser->id]), [
            'employee_id' => $e1->id,
        ])
        ->assertOk();

    $this->assertDatabaseHas('user_employee', [
        'user_id' => (int) $targetUser->id,
        'employee_id' => (int) $e1->id,
    ]);

    $this->actingAsUserInCompany($adminUser, $companyA)
        ->deleteJson(route('admin.user_employees.destroy', [
            'user' => $targetUser->id,
            'employee' => $e1->id,
        ]))
        ->assertOk();

    $this->assertDatabaseMissing('user_employee', [
        'user_id' => (int) $targetUser->id,
        'employee_id' => (int) $e1->id,
    ]);
});

it('rejects cross-tenant employee attach', function (): void {
    $tenantOne = TenantGroup::factory()->create();
    $tenantTwo = TenantGroup::factory()->create();

    $companyA = Company::factory()->create(['tenant_group_id' => $tenantOne->id, 'active' => true]);
    $companyB = Company::factory()->create(['tenant_group_id' => $tenantTwo->id, 'active' => true]);

    $eX = Employee::factory()->create([
        'company_id' => $companyB->id,
        'active' => true,
    ]);

    CompanyEmployee::query()->updateOrCreate(
        ['company_id' => $companyB->id, 'employee_id' => $eX->id],
        ['active' => true]
    );

    $adminUser = $this->createAdminUser($companyA);
    $adminUser->givePermissionTo(['user_employees.create']);

    $targetUser = User::factory()->create();
    $targetUser->companies()->syncWithoutDetaching([$companyA->id]);

    $this->actingAsUserInCompany($adminUser, $companyA)
        ->postJson(route('admin.user_employees.store', ['user' => $targetUser->id]), [
            'employee_id' => $eX->id,
        ])
        ->assertStatus(422);

    $this->assertDatabaseMissing('user_employee', [
        'user_id' => (int) $targetUser->id,
        'employee_id' => (int) $eX->id,
    ]);
});

it('returns 403 when permission is missing', function (): void {
    $tenantOne = TenantGroup::factory()->create();
    $companyA = Company::factory()->create(['tenant_group_id' => $tenantOne->id, 'active' => true]);
    $employee = Employee::factory()->create(['company_id' => $companyA->id, 'active' => true]);

    CompanyEmployee::query()->updateOrCreate(
        ['company_id' => $companyA->id, 'employee_id' => $employee->id],
        ['active' => true]
    );

    $adminUser = $this->createAdminUser($companyA);
    /** @var Role $adminRole */
    $adminRole = Role::findByName('admin', 'web');
    $adminRole->revokePermissionTo('user_employees.create');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $adminUser->refresh();

    $targetUser = User::factory()->create();
    $targetUser->companies()->syncWithoutDetaching([$companyA->id]);

    $this->actingAsUserInCompany($adminUser, $companyA)
        ->postJson(route('admin.user_employees.store', ['user' => $targetUser->id]), [
            'employee_id' => $employee->id,
        ])
        ->assertForbidden();
});

it('bumps selector cache version after attach and detach', function (): void {
    Cache::flush();

    $tenantOne = TenantGroup::factory()->create();
    $companyA = Company::factory()->create(['tenant_group_id' => $tenantOne->id, 'active' => true]);

    $employee = Employee::factory()->create([
        'company_id' => $companyA->id,
        'active' => true,
    ]);

    CompanyEmployee::query()->updateOrCreate(
        ['company_id' => $companyA->id, 'employee_id' => $employee->id],
        ['active' => true]
    );

    $adminUser = $this->createAdminUser($companyA);
    $adminUser->givePermissionTo([
        'user_employees.create',
        'user_employees.delete',
        'user_employees.viewAny',
    ]);

    $targetUser = User::factory()->create();
    $targetUser->companies()->syncWithoutDetaching([$companyA->id]);

    /** @var CacheVersionService $versions */
    $versions = app(CacheVersionService::class);
    $namespace = "tenant:{$tenantOne->id}:selectors.companies";
    $versionBeforeAttach = $versions->get($namespace);

    $this->actingAsUserInCompany($adminUser, $companyA)
        ->postJson(route('admin.user_employees.store', ['user' => $targetUser->id]), [
            'employee_id' => $employee->id,
        ])
        ->assertOk();

    $versionAfterAttach = $versions->get($namespace);
    expect($versionAfterAttach)->toBeGreaterThan($versionBeforeAttach);

    $this->actingAsUserInCompany($adminUser, $companyA)
        ->deleteJson(route('admin.user_employees.destroy', [
            'user' => $targetUser->id,
            'employee' => $employee->id,
        ]))
        ->assertOk();

    $versionAfterDetach = $versions->get($namespace);
    expect($versionAfterDetach)->toBeGreaterThan($versionAfterAttach);
});
