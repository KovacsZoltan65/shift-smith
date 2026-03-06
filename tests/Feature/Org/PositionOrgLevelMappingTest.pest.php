<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Employee;
use App\Models\Position;
use App\Models\PositionOrgLevel;
use App\Models\TenantGroup;
use App\Models\User;
use App\Services\Cache\CacheNamespaces;
use App\Services\Cache\CacheVersionService;
use App\Services\Org\PositionNormalizer;
use App\Services\Org\PositionOrgLevelService;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('normalizes position labels case and accent-insensitive', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $tenant->makeCurrent();

    app(PositionOrgLevelService::class)->upsertMapping(
        companyId: (int) $company->id,
        positionLabel: 'Osztályvezető',
        orgLevel: 'department_head',
        active: true
    );

    $key = PositionNormalizer::key('  osztalyvezeto ');
    expect($key)->toBe('osztalyvezeto');

    $resolved = app(PositionOrgLevelService::class)->resolveOrgLevel((int) $company->id, 'OSZTALYVEZETO');
    expect($resolved)->toBe('department_head');
});

it('returns staff fallback when mapping is missing', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $tenant->makeCurrent();

    $resolved = app(PositionOrgLevelService::class)->resolveOrgLevel((int) $company->id, 'ismeretlen munkakor');
    expect($resolved)->toBe('staff');
});

it('keeps mapping company scoped', function (): void {
    $tenant = TenantGroup::factory()->create();
    $companyA = Company::factory()->create(['tenant_group_id' => $tenant->id]);
    $companyB = Company::factory()->create(['tenant_group_id' => $tenant->id]);
    $tenant->makeCurrent();

    app(PositionOrgLevelService::class)->upsertMapping((int) $companyA->id, 'Manager', 'manager', true);

    expect(app(PositionOrgLevelService::class)->resolveOrgLevel((int) $companyA->id, 'Manager'))->toBe('manager');
    expect(app(PositionOrgLevelService::class)->resolveOrgLevel((int) $companyB->id, 'Manager'))->toBe('staff');
});

it('isolates mapping CRUD between tenants', function (): void {
    [$tenantA, $companyA] = $this->createTenantWithCompany();
    [$tenantB, $companyB] = $this->createTenantWithCompany();

    $adminA = $this->createAdminUser($companyA);
    $adminB = $this->createAdminUser($companyB);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $adminA->refresh();
    $adminB->refresh();

    $this->actingAsUserInCompany($adminA, $companyA)
        ->postJson(route('admin.position_org_levels.store'), [
            'position_label' => 'Manager',
            'org_level' => 'manager',
            'active' => true,
        ])
        ->assertCreated();

    $this->actingAsUserInCompany($adminB, $companyB)
        ->getJson(route('admin.position_org_levels.fetch'))
        ->assertOk()
        ->assertJsonMissing(['position_label' => 'Manager', 'org_level' => 'manager']);
});

it('auto-fills employee org_level from position mapping on store and update', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $tenant->makeCurrent();
    $admin = $this->createSuperadminUser();
    $admin->companies()->syncWithoutDetaching([$company->id]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $admin->refresh();

    app(PositionOrgLevelService::class)->upsertMapping((int) $company->id, 'Igazgató', 'ceo', true);
    app(PositionOrgLevelService::class)->upsertMapping((int) $company->id, 'Dolgozó', 'staff', true);

    $ceoPosition = Position::factory()->create(['company_id' => $company->id, 'name' => 'Igazgató']);
    $staffPosition = Position::factory()->create(['company_id' => $company->id, 'name' => 'Dolgozó']);

    $storeRes = $this->actingAsUserInCompany($admin, $company)
        ->postJson(route('employees.store'), [
            'company_id' => $company->id,
            'first_name' => 'Teszt',
            'last_name' => 'Dolgozo',
            'email' => 'map.employee@test.local',
            'position_id' => $ceoPosition->id,
            'birth_date' => '1990-01-01',
            'active' => true,
        ])
        ->assertCreated();

    $employeeId = (int) $storeRes->json('data.id');
    expect(Employee::query()->findOrFail($employeeId)->org_level)->toBe('ceo');

    $this->actingAsUserInCompany($admin, $company)
        ->putJson(route('employees.update', $employeeId), [
            'company_id' => $company->id,
            'first_name' => 'Teszt',
            'last_name' => 'Dolgozo',
            'email' => 'map.employee@test.local',
            'position_id' => $staffPosition->id,
            'birth_date' => '1990-01-01',
            'active' => true,
        ])
        ->assertOk();

    expect(Employee::query()->findOrFail($employeeId)->org_level)->toBe('staff');
});

it('blocks assigning supervisor for ceo level employees', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $tenant->makeCurrent();
    $admin = $this->createAdminUser($company);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $admin->refresh();

    app(PositionOrgLevelService::class)->upsertMapping((int) $company->id, 'Igazgató', 'ceo', true);

    $ceoPosition = Position::factory()->create(['company_id' => $company->id, 'name' => 'Igazgató']);
    $manager = Employee::factory()->create(['company_id' => $company->id, 'org_level' => 'manager']);

    $create = $this->actingAsUserInCompany($admin, $company)
        ->postJson(route('employees.store'), [
            'company_id' => $company->id,
            'first_name' => 'Ceo',
            'last_name' => 'User',
            'email' => 'ceo.block@test.local',
            'position_id' => $ceoPosition->id,
            'birth_date' => '1991-01-01',
        ])
        ->assertCreated();

    $employeeId = (int) $create->json('data.id');

    $this->actingAsUserInCompany($admin, $company)
        ->postJson(route('employees.supervisor.assign', $employeeId), [
            'supervisor_employee_id' => $manager->id,
            'valid_from' => '2026-03-01',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['supervisor_employee_id']);
});

it('bumps cache version after mapping CRUD', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $admin = $this->createAdminUser($company);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $admin->refresh();

    $namespace = CacheNamespaces::tenantOrgHierarchy((int) $tenant->id, (int) $company->id).':position_level_map';
    $versioner = app(CacheVersionService::class);
    $before = $versioner->get($namespace);

    $store = $this->actingAsUserInCompany($admin, $company)
        ->postJson(route('admin.position_org_levels.store'), [
            'position_label' => 'Manager',
            'org_level' => 'manager',
            'active' => true,
        ])
        ->assertCreated();

    $id = (int) $store->json('data.id');

    $this->actingAsUserInCompany($admin, $company)
        ->putJson(route('admin.position_org_levels.update', $id), [
            'position_label' => 'Manager',
            'org_level' => 'department_head',
            'active' => true,
        ])
        ->assertOk();

    $this->actingAsUserInCompany($admin, $company)
        ->deleteJson(route('admin.position_org_levels.destroy', $id))
        ->assertOk();

    $after = $versioner->get($namespace);
    expect($after)->toBeGreaterThan($before);
});

it('enforces permission on mapping CRUD', function (): void {
    [, $company] = $this->createTenantWithCompany();
    $user = User::factory()->create();
    $user->assignRole('user');
    $user->companies()->syncWithoutDetaching([$company->id]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $this->actingAsUserInCompany($user, $company)
        ->postJson(route('admin.position_org_levels.store'), [
            'position_label' => 'Manager',
            'org_level' => 'manager',
            'active' => true,
        ])
        ->assertForbidden();
});
