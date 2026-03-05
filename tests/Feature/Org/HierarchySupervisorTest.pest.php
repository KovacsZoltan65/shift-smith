<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Employee;
use App\Models\TenantGroup;
use App\Models\User;
use App\Models\UserEmployee;
use App\Services\Cache\CacheNamespaces;
use App\Services\Cache\CacheVersionService;
use App\Services\HierarchyAuthorizationService;
use App\Services\HierarchyIntegrityService;
use Carbon\CarbonImmutable;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('enforces tenant isolation when assigning supervisor', function (): void {
    [$tenantA, $companyA] = $this->createTenantWithCompany();
    [$tenantB, $companyB] = $this->createTenantWithCompany();

    $userA = $this->createAdminUser($companyA);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $userA->refresh();

    $employeeA = Employee::factory()->create(['company_id' => $companyA->id, 'org_level' => Employee::ORG_LEVEL_STAFF]);
    $foreignSupervisor = Employee::factory()->create(['company_id' => $companyB->id, 'org_level' => Employee::ORG_LEVEL_MANAGER]);

    $this->actingAsUserInCompany($userA, $companyA)
        ->postJson(route('employees.supervisor.assign', $employeeA->id), [
            'supervisor_employee_id' => $foreignSupervisor->id,
            'valid_from' => '2026-03-01',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['supervisor_employee_id']);
});

it('enforces company scope inside the same tenant for supervisor assignment', function (): void {
    $tenant = TenantGroup::factory()->create();
    $companyA = Company::factory()->create(['tenant_group_id' => $tenant->id]);
    $companyB = Company::factory()->create(['tenant_group_id' => $tenant->id]);

    $userA = $this->createAdminUser($companyA);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $userA->refresh();

    $employeeA = Employee::factory()->create(['company_id' => $companyA->id, 'org_level' => Employee::ORG_LEVEL_STAFF]);
    $supervisorB = Employee::factory()->create(['company_id' => $companyB->id, 'org_level' => Employee::ORG_LEVEL_MANAGER]);

    $this->actingAsUserInCompany($userA, $companyA)
        ->postJson(route('employees.supervisor.assign', $employeeA->id), [
            'supervisor_employee_id' => $supervisorB->id,
            'valid_from' => '2026-03-01',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['supervisor_employee_id']);
});

it('enforces ceo and non-ceo supervisor rules', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($company);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $ceo = Employee::factory()->create(['company_id' => $company->id, 'org_level' => Employee::ORG_LEVEL_CEO]);
    $staff = Employee::factory()->create(['company_id' => $company->id, 'org_level' => Employee::ORG_LEVEL_STAFF]);
    $manager = Employee::factory()->create(['company_id' => $company->id, 'org_level' => Employee::ORG_LEVEL_MANAGER]);

    $this->actingAsUserInCompany($user, $company)
        ->postJson(route('employees.supervisor.assign', $ceo->id), [
            'supervisor_employee_id' => $manager->id,
            'valid_from' => '2026-03-01',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['supervisor_employee_id']);

    $this->actingAsUserInCompany($user, $company)
        ->postJson(route('employees.supervisor.assign', $staff->id), [
            'supervisor_employee_id' => null,
            'valid_from' => '2026-03-01',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['supervisor_employee_id']);
});

it('blocks overlapping periods and cycle creation', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($company);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $a = Employee::factory()->create(['company_id' => $company->id, 'org_level' => Employee::ORG_LEVEL_STAFF]);
    $b = Employee::factory()->create(['company_id' => $company->id, 'org_level' => Employee::ORG_LEVEL_MANAGER]);
    $c = Employee::factory()->create(['company_id' => $company->id, 'org_level' => Employee::ORG_LEVEL_MANAGER]);

    $this->actingAsUserInCompany($user, $company)
        ->postJson(route('employees.supervisor.assign', $a->id), [
            'supervisor_employee_id' => $b->id,
            'valid_from' => '2026-01-01',
        ])->assertCreated();

    expect(fn () => app(HierarchyIntegrityService::class)->validateNewSupervisorRelationOrFail(
        companyId: (int) $company->id,
        employeeId: (int) $a->id,
        supervisorEmployeeId: (int) $c->id,
        validFrom: CarbonImmutable::parse('2026-01-10'),
        enforceOverlap: true
    ))->toThrow(\Illuminate\Validation\ValidationException::class);

    $this->actingAsUserInCompany($user, $company)
        ->postJson(route('employees.supervisor.assign', $b->id), [
            'supervisor_employee_id' => $a->id,
            'valid_from' => '2026-02-01',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['supervisor_employee_id']);

    $this->actingAsUserInCompany($user, $company)
        ->postJson(route('employees.supervisor.assign', $b->id), [
            'supervisor_employee_id' => $c->id,
            'valid_from' => '2026-03-01',
        ])->assertCreated();

    $this->actingAsUserInCompany($user, $company)
        ->postJson(route('employees.supervisor.assign', $c->id), [
            'supervisor_employee_id' => $a->id,
            'valid_from' => '2026-03-02',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['supervisor_employee_id']);
});

it('enforces self/direct/recursive authorization and no subordinate-to-supervisor read', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();

    $supervisorUser = $this->createAdminUser($company);
    $directUser = User::factory()->create();
    $childUser = User::factory()->create();

    $supervisor = Employee::factory()->create(['company_id' => $company->id, 'org_level' => Employee::ORG_LEVEL_MANAGER]);
    $direct = Employee::factory()->create(['company_id' => $company->id, 'org_level' => Employee::ORG_LEVEL_STAFF]);
    $child = Employee::factory()->create(['company_id' => $company->id, 'org_level' => Employee::ORG_LEVEL_STAFF]);

    $directUser->assignRole('admin');
    $childUser->assignRole('admin');
    $directUser->companies()->syncWithoutDetaching([$company->id]);
    $childUser->companies()->syncWithoutDetaching([$company->id]);

    UserEmployee::query()->updateOrCreate(
        ['user_id' => $supervisorUser->id, 'company_id' => $company->id],
        ['employee_id' => $supervisor->id, 'active' => true]
    );
    UserEmployee::query()->updateOrCreate(
        ['user_id' => $directUser->id, 'company_id' => $company->id],
        ['employee_id' => $direct->id, 'active' => true]
    );
    UserEmployee::query()->updateOrCreate(
        ['user_id' => $childUser->id, 'company_id' => $company->id],
        ['employee_id' => $child->id, 'active' => true]
    );

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $supervisorUser->refresh();
    $directUser->refresh();
    $childUser->refresh();

    $this->actingAsUserInCompany($supervisorUser, $company)
        ->postJson(route('employees.supervisor.assign', $direct->id), [
            'supervisor_employee_id' => $supervisor->id,
            'valid_from' => '2026-01-01',
        ])->assertCreated();

    $this->actingAsUserInCompany($supervisorUser, $company)
        ->postJson(route('employees.supervisor.assign', $child->id), [
            'supervisor_employee_id' => $direct->id,
            'valid_from' => '2026-01-01',
        ])->assertCreated();

    CompanySetting::query()->updateOrCreate(
        ['company_id' => $company->id, 'key' => 'org.hierarchy.recursive_supervisor_access'],
        ['value' => false, 'type' => 'bool', 'group' => 'org.hierarchy']
    );
    app(CacheVersionService::class)->bump("effective_settings:{$company->id}:all");

    $this->actingAsUserInCompany($directUser, $company)
        ->putJson(route('employees.update', $direct->id), [
            'company_id' => $company->id,
            'first_name' => $direct->first_name,
            'last_name' => $direct->last_name,
            'email' => $direct->email,
            'birth_date' => '1990-01-01',
            'org_level' => Employee::ORG_LEVEL_STAFF,
        ])->assertOk();

    $this->actingAsUserInCompany($directUser, $company)
        ->getJson(route('employees.by_id', $supervisor->id))
        ->assertForbidden();

    $this->actingAsUserInCompany($supervisorUser, $company)
        ->putJson(route('employees.update', $direct->id), [
            'company_id' => $company->id,
            'first_name' => $direct->first_name,
            'last_name' => $direct->last_name,
            'email' => $direct->email,
            'birth_date' => '1990-01-01',
            'org_level' => Employee::ORG_LEVEL_STAFF,
        ])->assertOk();

    $this->actingAsUserInCompany($supervisorUser, $company)
        ->putJson(route('employees.update', $child->id), [
            'company_id' => $company->id,
            'first_name' => $child->first_name,
            'last_name' => $child->last_name,
            'email' => $child->email,
            'birth_date' => '1990-01-01',
            'org_level' => Employee::ORG_LEVEL_STAFF,
        ])->assertForbidden();

    CompanySetting::query()->updateOrCreate(
        ['company_id' => $company->id, 'key' => 'org.hierarchy.recursive_supervisor_access'],
        ['value' => true, 'type' => 'bool', 'group' => 'org.hierarchy']
    );
    app(CacheVersionService::class)->bump("effective_settings:{$company->id}:all");

    $this->actingAsUserInCompany($supervisorUser, $company)
        ->putJson(route('employees.update', $child->id), [
            'company_id' => $company->id,
            'first_name' => $child->first_name,
            'last_name' => $child->last_name,
            'email' => $child->email,
            'birth_date' => '1990-01-01',
            'org_level' => Employee::ORG_LEVEL_STAFF,
        ])->assertOk();
});

it('supports date-based historical authorization and bumps hierarchy cache version', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($company);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $supervisorOne = Employee::factory()->create(['company_id' => $company->id, 'org_level' => Employee::ORG_LEVEL_MANAGER]);
    $supervisorTwo = Employee::factory()->create(['company_id' => $company->id, 'org_level' => Employee::ORG_LEVEL_MANAGER]);
    $target = Employee::factory()->create(['company_id' => $company->id, 'org_level' => Employee::ORG_LEVEL_STAFF]);

    $userOne = User::factory()->create();
    $userTwo = User::factory()->create();
    $userOne->assignRole('admin');
    $userTwo->assignRole('admin');
    $userOne->companies()->syncWithoutDetaching([$company->id]);
    $userTwo->companies()->syncWithoutDetaching([$company->id]);

    UserEmployee::query()->updateOrCreate(
        ['user_id' => $userOne->id, 'company_id' => $company->id],
        ['employee_id' => $supervisorOne->id, 'active' => true]
    );
    UserEmployee::query()->updateOrCreate(
        ['user_id' => $userTwo->id, 'company_id' => $company->id],
        ['employee_id' => $supervisorTwo->id, 'active' => true]
    );

    $versionService = app(CacheVersionService::class);
    $namespace = CacheNamespaces::tenantOrgHierarchy((int) $tenant->id, (int) $company->id).':hierarchy';
    $beforeVersion = $versionService->get($namespace);

    $this->actingAsUserInCompany($user, $company)
        ->postJson(route('employees.supervisor.assign', $target->id), [
            'supervisor_employee_id' => $supervisorOne->id,
            'valid_from' => '2026-01-01',
        ])->assertCreated();

    $this->actingAsUserInCompany($user, $company)
        ->postJson(route('employees.supervisor.assign', $target->id), [
            'supervisor_employee_id' => $supervisorTwo->id,
            'valid_from' => '2026-02-01',
        ])->assertCreated();

    $afterVersion = $versionService->get($namespace);
    expect($afterVersion)->toBeGreaterThan($beforeVersion);

    $authorization = app(HierarchyAuthorizationService::class);
    $oldDate = CarbonImmutable::parse('2026-01-15');
    $newDate = CarbonImmutable::parse('2026-02-15');

    expect($authorization->canManageEmployee($userOne, $target, $oldDate))->toBeTrue();
    expect($authorization->canManageEmployee($userOne, $target, $newDate))->toBeFalse();
    expect($authorization->canManageEmployee($userTwo, $target, $newDate))->toBeTrue();
});
