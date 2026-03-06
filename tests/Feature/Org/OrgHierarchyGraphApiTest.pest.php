<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Employee;
use App\Models\TenantGroup;
use App\Models\User;
use App\Services\EmployeeSupervisorService;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('returns 403 for hierarchy graph without permission', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $tenant->makeCurrent();

    /** @var User $user */
    $user = User::factory()->create();
    $user->assignRole('user');
    $user->companies()->syncWithoutDetaching([$company->id]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $this->actingAsUserInCompany($user, $company)
        ->getJson(route('org.hierarchy.graph', ['company_id' => $company->id]))
        ->assertForbidden();
});

it('returns graph JSON for authorized user', function (): void {
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

    app(EmployeeSupervisorService::class)->assignSupervisor(
        companyId: (int) $company->id,
        employeeId: (int) $manager->id,
        supervisorEmployeeId: (int) $ceo->id,
        validFrom: now()->subDays(10)->toDateString(),
        actorUserId: (int) $admin->id
    );

    $this->actingAsUserInCompany($admin, $company)
        ->getJson(route('org.hierarchy.graph', ['company_id' => $company->id, 'at_date' => now()->toDateString(), 'depth' => 1]))
        ->assertOk()
        ->assertJsonStructure([
            'message',
            'data' => [
                'nodes' => [
                    '*' => ['id', 'label', 'position', 'org_level', 'direct_count', 'total_count', 'has_supervisor', 'is_root'],
                ],
                'edges' => [
                    '*' => ['source', 'target'],
                ],
                'meta' => ['root_id', 'company_id', 'at_date', 'depth', 'empty'],
            ],
        ]);
});

it('enforces company scope and blocks mismatched company query', function (): void {
    $tenant = TenantGroup::factory()->create();
    $companyA = Company::factory()->create(['tenant_group_id' => $tenant->id]);
    $companyB = Company::factory()->create(['tenant_group_id' => $tenant->id]);
    $tenant->makeCurrent();

    $admin = $this->createAdminUser($companyA);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $admin->refresh();

    $this->actingAsUserInCompany($admin, $companyA)
        ->getJson(route('org.hierarchy.graph', ['company_id' => $companyB->id]))
        ->assertForbidden();
});

it('keeps tenant isolation in graph data', function (): void {
    [$tenantA, $companyA] = $this->createTenantWithCompany();
    [$tenantB, $companyB] = $this->createTenantWithCompany();
    $tenantA->makeCurrent();

    $adminA = $this->createAdminUser($companyA);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $adminA->refresh();

    $ceoA = Employee::factory()->create([
        'company_id' => $companyA->id,
        'org_level' => Employee::ORG_LEVEL_CEO,
    ]);
    $staffA = Employee::factory()->create([
        'company_id' => $companyA->id,
        'org_level' => Employee::ORG_LEVEL_STAFF,
    ]);
    app(EmployeeSupervisorService::class)->assignSupervisor(
        companyId: (int) $companyA->id,
        employeeId: (int) $staffA->id,
        supervisorEmployeeId: (int) $ceoA->id,
        validFrom: now()->subDays(10)->toDateString(),
        actorUserId: (int) $adminA->id
    );

    $foreignEmployee = Employee::factory()->create([
        'company_id' => $companyB->id,
        'org_level' => Employee::ORG_LEVEL_CEO,
    ]);

    $response = $this->actingAsUserInCompany($adminA, $companyA)
        ->getJson(route('org.hierarchy.graph', ['company_id' => $companyA->id]))
        ->assertOk()
        ->json('data.nodes');

    $nodeIds = collect($response)->pluck('id')->map(fn ($id): int => (int) $id)->all();
    expect($nodeIds)->not->toContain((int) $foreignEmployee->id);
    expect($companyA->tenant_group_id)->not->toBe($companyB->tenant_group_id);
});
