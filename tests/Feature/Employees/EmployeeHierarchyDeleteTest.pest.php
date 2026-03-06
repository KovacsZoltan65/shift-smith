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

function adminEmployee(User $user, Company $company): Employee
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

it('returns 403 for delete preview without permission', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $tenant->makeCurrent();

    /** @var User $user */
    $user = User::factory()->create();
    $user->assignRole('user');
    $user->companies()->syncWithoutDetaching([$company->id]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $employee = Employee::factory()->create(['company_id' => $company->id, 'org_level' => Employee::ORG_LEVEL_STAFF]);

    $this->actingAsUserInCompany($user, $company)
        ->getJson(route('employees.delete_preview', [
            'employee' => $employee->id,
            'company_id' => $company->id,
        ]))
        ->assertForbidden();
});

it('returns delete preview with correct affected count', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $tenant->makeCurrent();
    $admin = $this->createAdminUser($company);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $admin->refresh();
    $adminEmployee = adminEmployee($admin, $company);

    $leader = Employee::factory()->create(['company_id' => $company->id, 'org_level' => Employee::ORG_LEVEL_MANAGER]);
    $childA = Employee::factory()->create(['company_id' => $company->id, 'org_level' => Employee::ORG_LEVEL_STAFF]);
    $childB = Employee::factory()->create(['company_id' => $company->id, 'org_level' => Employee::ORG_LEVEL_STAFF]);

    $svc = app(EmployeeSupervisorService::class);
    $svc->assignSupervisor((int) $company->id, (int) $leader->id, (int) $adminEmployee->id, '2026-01-01', (int) $admin->id);
    $svc->assignSupervisor((int) $company->id, (int) $childA->id, (int) $leader->id, '2026-01-01', (int) $admin->id);
    $svc->assignSupervisor((int) $company->id, (int) $childB->id, (int) $leader->id, '2026-01-01', (int) $admin->id);

    $this->actingAsUserInCompany($admin, $company)
        ->getJson(route('employees.delete_preview', [
            'employee' => $leader->id,
            'company_id' => $company->id,
            'effective_from' => '2026-03-06',
            'strategy' => 'none',
        ]))
        ->assertOk()
        ->assertJsonPath('data.subordinate_count', 2)
        ->assertJsonPath('data.affected_count', 2);
});

it('soft deletes a simple employee and closes active supervisor relation', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $tenant->makeCurrent();
    $admin = $this->createAdminUser($company);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $admin->refresh();
    $adminEmployee = adminEmployee($admin, $company);

    $employee = Employee::factory()->create(['company_id' => $company->id, 'org_level' => Employee::ORG_LEVEL_STAFF]);

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
        ->assertOk()
        ->assertJsonPath('data.success', true);

    $this->assertSoftDeleted('employees', ['id' => $employee->id]);
    $rows = app(EmployeeSupervisorRepositoryInterface::class)->listSupervisorHistory((int) $company->id, (int) $employee->id);
    expect($rows)->toHaveCount(1);
    expect($rows[0]->valid_to?->format('Y-m-d'))->toBe('2026-03-05');
});

it('deletes a leader without subordinates', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $tenant->makeCurrent();
    $admin = $this->createAdminUser($company);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $admin->refresh();
    $adminEmployee = adminEmployee($admin, $company);

    $leader = Employee::factory()->create(['company_id' => $company->id, 'org_level' => Employee::ORG_LEVEL_MANAGER]);

    app(EmployeeSupervisorService::class)->assignSupervisor((int) $company->id, (int) $leader->id, (int) $adminEmployee->id, '2026-01-01', (int) $admin->id);

    $this->actingAsUserInCompany($admin, $company)
        ->deleteJson(route('employees.destroy', $leader->id), [
            'company_id' => $company->id,
            'effective_from' => '2026-03-06',
            'strategy' => 'none',
        ])
        ->assertOk();

    $this->assertSoftDeleted('employees', ['id' => $leader->id]);
});

it('blocks leader deletion with subordinates when strategy is none', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $tenant->makeCurrent();
    $admin = $this->createAdminUser($company);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $admin->refresh();
    $adminEmployee = adminEmployee($admin, $company);

    $leader = Employee::factory()->create(['company_id' => $company->id, 'org_level' => Employee::ORG_LEVEL_MANAGER]);
    $child = Employee::factory()->create(['company_id' => $company->id, 'org_level' => Employee::ORG_LEVEL_STAFF]);
    app(EmployeeSupervisorService::class)->assignSupervisor((int) $company->id, (int) $leader->id, (int) $adminEmployee->id, '2026-01-01', (int) $admin->id);
    app(EmployeeSupervisorService::class)->assignSupervisor((int) $company->id, (int) $child->id, (int) $leader->id, '2026-01-01', (int) $admin->id);

    $this->actingAsUserInCompany($admin, $company)
        ->deleteJson(route('employees.destroy', $leader->id), [
            'company_id' => $company->id,
            'effective_from' => '2026-03-06',
            'strategy' => 'none',
        ])
        ->assertStatus(422);
});

it('reassigns subordinates to old supervisor when deleting a leader', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $tenant->makeCurrent();
    $admin = $this->createAdminUser($company);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $admin->refresh();
    $adminEmployee = adminEmployee($admin, $company);

    $leader = Employee::factory()->create(['company_id' => $company->id, 'org_level' => Employee::ORG_LEVEL_MANAGER]);
    $child = Employee::factory()->create(['company_id' => $company->id, 'org_level' => Employee::ORG_LEVEL_STAFF]);

    $svc = app(EmployeeSupervisorService::class);
    $svc->assignSupervisor((int) $company->id, (int) $leader->id, (int) $adminEmployee->id, '2026-01-01', (int) $admin->id);
    $svc->assignSupervisor((int) $company->id, (int) $child->id, (int) $leader->id, '2026-01-01', (int) $admin->id);

    $versioner = app(CacheVersionService::class);
    $namespace = CacheNamespaces::tenantOrgHierarchy((int) $tenant->id, (int) $company->id).':hierarchy';
    $before = $versioner->get($namespace);

    $this->actingAsUserInCompany($admin, $company)
        ->deleteJson(route('employees.destroy', $leader->id), [
            'company_id' => $company->id,
            'effective_from' => '2026-03-06',
            'strategy' => 'reassign_to_old_supervisor',
        ])
        ->assertOk()
        ->assertJsonPath('data.affected_count', 1);

    $after = $versioner->get($namespace);
    expect($after)->toBeGreaterThan($before);
    $this->assertSoftDeleted('employees', ['id' => $leader->id]);

    $active = app(EmployeeSupervisorRepositoryInterface::class)
        ->findActiveSupervisor((int) $company->id, (int) $child->id, CarbonImmutable::parse('2026-03-10'));
    expect((int) $active?->supervisor_employee_id)->toBe((int) $adminEmployee->id);
});

it('reassigns subordinates to a specific supervisor when deleting a leader', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $tenant->makeCurrent();
    $admin = $this->createAdminUser($company);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $admin->refresh();
    $adminEmployee = adminEmployee($admin, $company);

    $targetSupervisor = Employee::factory()->create(['company_id' => $company->id, 'org_level' => Employee::ORG_LEVEL_MANAGER]);
    $leader = Employee::factory()->create(['company_id' => $company->id, 'org_level' => Employee::ORG_LEVEL_MANAGER]);
    $child = Employee::factory()->create(['company_id' => $company->id, 'org_level' => Employee::ORG_LEVEL_STAFF]);

    $svc = app(EmployeeSupervisorService::class);
    $svc->assignSupervisor((int) $company->id, (int) $leader->id, (int) $adminEmployee->id, '2026-01-01', (int) $admin->id);
    $svc->assignSupervisor((int) $company->id, (int) $child->id, (int) $leader->id, '2026-01-01', (int) $admin->id);

    $this->actingAsUserInCompany($admin, $company)
        ->deleteJson(route('employees.destroy', $leader->id), [
            'company_id' => $company->id,
            'effective_from' => '2026-03-06',
            'strategy' => 'reassign_to_specific_supervisor',
            'target_supervisor_employee_id' => $targetSupervisor->id,
        ])
        ->assertOk();

    $this->assertSoftDeleted('employees', ['id' => $leader->id]);
    $active = app(EmployeeSupervisorRepositoryInterface::class)
        ->findActiveSupervisor((int) $company->id, (int) $child->id, CarbonImmutable::parse('2026-03-10'));
    expect((int) $active?->supervisor_employee_id)->toBe((int) $targetSupervisor->id);
});

it('blocks deleting a ceo with active subordinates', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $tenant->makeCurrent();
    $admin = $this->createAdminUser($company);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $admin->refresh();
    $ceo = adminEmployee($admin, $company);
    $ceo->forceFill(['org_level' => Employee::ORG_LEVEL_CEO])->save();
    $child = Employee::factory()->create(['company_id' => $company->id, 'org_level' => Employee::ORG_LEVEL_MANAGER]);
    app(EmployeeSupervisorService::class)->assignSupervisor((int) $company->id, (int) $child->id, (int) $ceo->id, '2026-01-01', (int) $admin->id);

    $this->actingAsUserInCompany($admin, $company)
        ->deleteJson(route('employees.destroy', $ceo->id), [
            'company_id' => $company->id,
            'effective_from' => '2026-03-06',
            'strategy' => 'none',
        ])
        ->assertStatus(422);
});

it('deletes a ceo without subordinates', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $tenant->makeCurrent();
    $admin = $this->createAdminUser($company);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $admin->refresh();
    $ceo = adminEmployee($admin, $company);
    $ceo->forceFill(['org_level' => Employee::ORG_LEVEL_CEO])->save();

    $this->actingAsUserInCompany($admin, $company)
        ->deleteJson(route('employees.destroy', $ceo->id), [
            'company_id' => $company->id,
            'effective_from' => '2026-03-06',
            'strategy' => 'none',
        ])
        ->assertOk();

    $this->assertSoftDeleted('employees', ['id' => $ceo->id]);
});

it('enforces tenant isolation and company scope during delete', function (): void {
    [$tenantA, $companyA] = $this->createTenantWithCompany();
    $tenantA->makeCurrent();
    $admin = $this->createAdminUser($companyA);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $admin->refresh();

    $employee = Employee::factory()->create(['company_id' => $companyA->id, 'org_level' => Employee::ORG_LEVEL_STAFF]);

    $tenantB = TenantGroup::factory()->create();
    $companyB = Company::factory()->create(['tenant_group_id' => $tenantB->id]);

    $this->actingAsUserInCompany($admin, $companyA)
        ->deleteJson(route('employees.destroy', $employee->id), [
            'company_id' => $companyB->id,
            'effective_from' => '2026-03-06',
            'strategy' => 'none',
        ])
        ->assertForbidden();
});

it('blocks cycles when reassigning direct subordinate leaders to a specific supervisor', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $tenant->makeCurrent();
    $admin = $this->createAdminUser($company);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $admin->refresh();
    $adminEmployee = adminEmployee($admin, $company);

    $leader = Employee::factory()->create(['company_id' => $company->id, 'org_level' => Employee::ORG_LEVEL_MANAGER]);
    $childLeader = Employee::factory()->create(['company_id' => $company->id, 'org_level' => Employee::ORG_LEVEL_MANAGER]);
    $grandChild = Employee::factory()->create(['company_id' => $company->id, 'org_level' => Employee::ORG_LEVEL_STAFF]);

    $svc = app(EmployeeSupervisorService::class);
    $svc->assignSupervisor((int) $company->id, (int) $leader->id, (int) $adminEmployee->id, '2026-01-01', (int) $admin->id);
    $svc->assignSupervisor((int) $company->id, (int) $childLeader->id, (int) $leader->id, '2026-01-01', (int) $admin->id);
    $svc->assignSupervisor((int) $company->id, (int) $grandChild->id, (int) $childLeader->id, '2026-01-01', (int) $admin->id);

    $this->actingAsUserInCompany($admin, $company)
        ->deleteJson(route('employees.destroy', $leader->id), [
            'company_id' => $company->id,
            'effective_from' => '2026-03-06',
            'strategy' => 'reassign_to_specific_supervisor',
            'target_supervisor_employee_id' => $grandChild->id,
        ])
        ->assertStatus(422);
});
