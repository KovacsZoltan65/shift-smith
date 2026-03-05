<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Employee;
use App\Models\TenantGroup;
use App\Models\User;
use App\Repositories\EmployeeSupervisorRepositoryInterface;
use App\Services\Cache\CacheNamespaces;
use App\Services\Cache\CacheVersionService;
use App\Services\EmployeeSupervisorService;
use Carbon\CarbonImmutable;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('returns 403 for move preview without permission', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $tenant->makeCurrent();

    /** @var User $user */
    $user = User::factory()->create();
    $user->assignRole('user');
    $user->companies()->syncWithoutDetaching([$company->id]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $employee = Employee::factory()->create(['company_id' => $company->id, 'org_level' => Employee::ORG_LEVEL_STAFF]);
    $supervisor = Employee::factory()->create(['company_id' => $company->id, 'org_level' => Employee::ORG_LEVEL_MANAGER]);

    $this->actingAsUserInCompany($user, $company)
        ->getJson(route('org.hierarchy.move.preview', [
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'new_supervisor_employee_id' => $supervisor->id,
            'mode' => 'employee_only',
        ]))
        ->assertForbidden();
});

it('moves employee with history close and create', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $tenant->makeCurrent();
    $admin = $this->createAdminUser($company);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $admin->refresh();

    $employee = Employee::factory()->create(['company_id' => $company->id, 'org_level' => Employee::ORG_LEVEL_STAFF]);
    $supervisorOne = Employee::factory()->create(['company_id' => $company->id, 'org_level' => Employee::ORG_LEVEL_MANAGER]);
    $supervisorTwo = Employee::factory()->create(['company_id' => $company->id, 'org_level' => Employee::ORG_LEVEL_MANAGER]);

    app(EmployeeSupervisorService::class)->assignSupervisor(
        companyId: (int) $company->id,
        employeeId: (int) $employee->id,
        supervisorEmployeeId: (int) $supervisorOne->id,
        validFrom: '2026-01-01',
        actorUserId: (int) $admin->id
    );

    $namespace = CacheNamespaces::tenantOrgHierarchy((int) $tenant->id, (int) $company->id).':hierarchy';
    $cacheVersion = app(CacheVersionService::class);
    $before = $cacheVersion->get($namespace);

    $this->actingAsUserInCompany($admin, $company)
        ->postJson(route('org.hierarchy.move'), [
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'new_supervisor_employee_id' => $supervisorTwo->id,
            'mode' => 'employee_only',
            'effective_from' => '2026-02-01',
            'at_date' => '2026-01-15',
        ])
        ->assertOk()
        ->assertJsonPath('data.success', true)
        ->assertJsonPath('data.affected_count', 1);

    $after = $cacheVersion->get($namespace);
    expect($after)->toBeGreaterThan($before);

    $rows = app(EmployeeSupervisorRepositoryInterface::class)->listSupervisorHistory((int) $company->id, (int) $employee->id);
    expect($rows)->toHaveCount(2);
    expect((string) $rows[0]->valid_from?->format('Y-m-d'))->toBe('2026-02-01');
    expect((string) $rows[1]->valid_to?->format('Y-m-d'))->toBe('2026-01-31');
});

it('moves leader without touching subtree in leader_with_subordinates mode', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $tenant->makeCurrent();
    $admin = $this->createAdminUser($company);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $admin->refresh();

    $ceo = Employee::factory()->create(['company_id' => $company->id, 'org_level' => Employee::ORG_LEVEL_CEO]);
    $newSupervisor = Employee::factory()->create(['company_id' => $company->id, 'org_level' => Employee::ORG_LEVEL_MANAGER]);
    $leader = Employee::factory()->create(['company_id' => $company->id, 'org_level' => Employee::ORG_LEVEL_MANAGER]);
    $staff = Employee::factory()->create(['company_id' => $company->id, 'org_level' => Employee::ORG_LEVEL_STAFF]);

    $svc = app(EmployeeSupervisorService::class);
    $svc->assignSupervisor((int) $company->id, (int) $leader->id, (int) $ceo->id, '2026-01-01', (int) $admin->id);
    $svc->assignSupervisor((int) $company->id, (int) $staff->id, (int) $leader->id, '2026-01-01', (int) $admin->id);

    $this->actingAsUserInCompany($admin, $company)
        ->postJson(route('org.hierarchy.move'), [
            'company_id' => $company->id,
            'employee_id' => $leader->id,
            'new_supervisor_employee_id' => $newSupervisor->id,
            'mode' => 'leader_with_subordinates',
            'effective_from' => '2026-02-01',
            'at_date' => '2026-01-15',
        ])
        ->assertOk();

    $leaderActive = app(EmployeeSupervisorRepositoryInterface::class)->findActiveSupervisor((int) $company->id, (int) $leader->id, CarbonImmutable::parse('2026-02-15'));
    $staffActive = app(EmployeeSupervisorRepositoryInterface::class)->findActiveSupervisor((int) $company->id, (int) $staff->id, CarbonImmutable::parse('2026-02-15'));

    expect((int) $leaderActive?->supervisor_employee_id)->toBe((int) $newSupervisor->id);
    expect((int) $staffActive?->supervisor_employee_id)->toBe((int) $leader->id);
});

it('reassigns direct children in move_subordinates_only mode', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $tenant->makeCurrent();
    $admin = $this->createAdminUser($company);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $admin->refresh();

    $sourceLeader = Employee::factory()->create(['company_id' => $company->id, 'org_level' => Employee::ORG_LEVEL_MANAGER]);
    $targetLeader = Employee::factory()->create(['company_id' => $company->id, 'org_level' => Employee::ORG_LEVEL_MANAGER]);
    $childA = Employee::factory()->create(['company_id' => $company->id, 'org_level' => Employee::ORG_LEVEL_STAFF]);
    $childB = Employee::factory()->create(['company_id' => $company->id, 'org_level' => Employee::ORG_LEVEL_STAFF]);

    $svc = app(EmployeeSupervisorService::class);
    $svc->assignSupervisor((int) $company->id, (int) $childA->id, (int) $sourceLeader->id, '2026-01-01', (int) $admin->id);
    $svc->assignSupervisor((int) $company->id, (int) $childB->id, (int) $sourceLeader->id, '2026-01-01', (int) $admin->id);

    $this->actingAsUserInCompany($admin, $company)
        ->postJson(route('org.hierarchy.move'), [
            'company_id' => $company->id,
            'employee_id' => $sourceLeader->id,
            'mode' => 'move_subordinates_only',
            'target_supervisor_for_subordinates' => $targetLeader->id,
            'effective_from' => '2026-02-01',
            'at_date' => '2026-01-20',
        ])
        ->assertOk()
        ->assertJsonPath('data.affected_count', 2);

    $a = app(EmployeeSupervisorRepositoryInterface::class)->findActiveSupervisor((int) $company->id, (int) $childA->id, CarbonImmutable::parse('2026-02-10'));
    $b = app(EmployeeSupervisorRepositoryInterface::class)->findActiveSupervisor((int) $company->id, (int) $childB->id, CarbonImmutable::parse('2026-02-10'));

    expect((int) $a?->supervisor_employee_id)->toBe((int) $targetLeader->id);
    expect((int) $b?->supervisor_employee_id)->toBe((int) $targetLeader->id);
});

it('blocks cycle and ceo supervisor rules during move', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $tenant->makeCurrent();
    $admin = $this->createAdminUser($company);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $admin->refresh();

    $ceo = Employee::factory()->create(['company_id' => $company->id, 'org_level' => Employee::ORG_LEVEL_CEO]);
    $leader = Employee::factory()->create(['company_id' => $company->id, 'org_level' => Employee::ORG_LEVEL_MANAGER]);
    $staff = Employee::factory()->create(['company_id' => $company->id, 'org_level' => Employee::ORG_LEVEL_STAFF]);

    $svc = app(EmployeeSupervisorService::class);
    $svc->assignSupervisor((int) $company->id, (int) $leader->id, (int) $ceo->id, '2026-01-01', (int) $admin->id);
    $svc->assignSupervisor((int) $company->id, (int) $staff->id, (int) $leader->id, '2026-01-01', (int) $admin->id);

    $this->actingAsUserInCompany($admin, $company)
        ->postJson(route('org.hierarchy.move'), [
            'company_id' => $company->id,
            'employee_id' => $leader->id,
            'new_supervisor_employee_id' => $staff->id,
            'mode' => 'employee_only',
            'effective_from' => '2026-03-01',
            'at_date' => '2026-02-01',
        ])
        ->assertStatus(422);

    $this->actingAsUserInCompany($admin, $company)
        ->postJson(route('org.hierarchy.move'), [
            'company_id' => $company->id,
            'employee_id' => $ceo->id,
            'new_supervisor_employee_id' => $leader->id,
            'mode' => 'employee_only',
            'effective_from' => '2026-03-01',
            'at_date' => '2026-02-01',
        ])
        ->assertStatus(422);
});

it('returns integrity report in company scope', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $tenant->makeCurrent();
    $admin = $this->createAdminUser($company);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $admin->refresh();

    $this->actingAsUserInCompany($admin, $company)
        ->getJson(route('org.hierarchy.integrity', [
            'company_id' => $company->id,
            'at_date' => now()->toDateString(),
        ]))
        ->assertOk()
        ->assertJsonStructure([
            'message',
            'data' => [
                'meta' => ['company_id', 'at_date'],
                'ok',
                'issues' => ['cycles', 'overlaps', 'missing_supervisor', 'multiple_active', 'ceo_has_supervisor'],
            ],
        ]);

    $tenantB = TenantGroup::factory()->create();
    $companyB = Company::factory()->create(['tenant_group_id' => $tenantB->id]);

    $this->actingAsUserInCompany($admin, $company)
        ->getJson(route('org.hierarchy.integrity', [
            'company_id' => $companyB->id,
            'at_date' => now()->toDateString(),
        ]))
        ->assertForbidden();
});
