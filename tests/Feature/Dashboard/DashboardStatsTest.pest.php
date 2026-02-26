<?php

declare(strict_types=1);

use App\Interfaces\EmployeeRepositoryInterface;
use App\Models\Company;
use App\Models\Employee;
use App\Models\TenantGroup;
use App\Models\User;
use App\Services\Cache\CacheVersionService;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
    TenantGroup::forgetCurrent();
});

afterEach(function (): void {
    TenantGroup::forgetCurrent();
});

it('scopes dashboard employees KPI to selected company and keeps tenant isolation', function (): void {
    $tenantGroupOne = TenantGroup::factory()->create();
    $tenantGroupTwo = TenantGroup::factory()->create();

    $companyA = Company::factory()->create(['tenant_group_id' => $tenantGroupOne->id, 'active' => true]);
    $companyB = Company::factory()->create(['tenant_group_id' => $tenantGroupOne->id, 'active' => true]);
    $companyC = Company::factory()->create(['tenant_group_id' => $tenantGroupTwo->id, 'active' => true]);

    Employee::factory()->count(3)->create(['company_id' => $companyA->id, 'active' => true]);
    Employee::factory()->count(1)->create(['company_id' => $companyB->id, 'active' => true]);
    Employee::factory()->count(7)->create(['company_id' => $companyC->id, 'active' => true]);

    $user = $this->createAdminUser($companyA);
    $user->companies()->syncWithoutDetaching([(int) $companyB->id]);

    $this->actingAsUserInCompany($user, $companyA)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('stats.employees', 3)
            ->where('stats.companies', 2)
        );

    $this->actingAsUserInCompany($user, $companyB)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('stats.employees', 1)
            ->where('stats.companies', 2)
        );
});

it('applies active and soft delete rules on employee KPI', function (): void {
    $tenantGroup = TenantGroup::factory()->create();
    $company = Company::factory()->create(['tenant_group_id' => $tenantGroup->id, 'active' => true]);

    $activeEmployee = Employee::factory()->create(['company_id' => $company->id, 'active' => true]);
    Employee::factory()->create(['company_id' => $company->id, 'active' => false]);

    $softDeleted = Employee::factory()->create(['company_id' => $company->id, 'active' => true]);
    $softDeleted->delete();

    $user = $this->createAdminUser($company);

    $response = $this->actingAsUserInCompany($user, $company)
        ->get(route('dashboard'))
        ->assertOk();

    $response->assertInertia(fn (Assert $page) => $page
        ->component('Dashboard')
        ->where('stats.employees', 1)
    );

    expect($activeEmployee->deleted_at)->toBeNull();
});

it('bumps dashboard stats cache version after employee mutation when dashboard cache is enabled', function (): void {
    config()->set('cache.enable_dashboard', true);

    $tenantGroup = TenantGroup::factory()->create();
    $company = Company::factory()->create(['tenant_group_id' => $tenantGroup->id, 'active' => true]);
    Employee::factory()->count(2)->create(['company_id' => $company->id, 'active' => true]);

    $user = $this->createAdminUser($company);

    $versioner = app(CacheVersionService::class);
    $employeeRepo = app(EmployeeRepositoryInterface::class);

    $this->actingAsUserInCompany($user, $company)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('stats.employees', 2)
        );

    $before = $versioner->get('dashboard.stats');

    $employeeRepo->store([
        'company_id' => (int) $company->id,
        'first_name' => 'Dash',
        'last_name' => 'Cache',
        'email' => 'dash.cache.'.uniqid().'@example.test',
        'address' => null,
        'position_id' => null,
        'phone' => null,
        'hired_at' => now()->toDateString(),
        'active' => true,
    ]);

    $after = $versioner->get('dashboard.stats');
    expect($after)->toBeGreaterThan($before);

    $this->actingAsUserInCompany($user, $company)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('stats.employees', 3)
        );
});

