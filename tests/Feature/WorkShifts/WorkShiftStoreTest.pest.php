<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\TenantGroup;
use App\Services\Cache\CacheVersionService;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('forbids work shift store without create permission', function (): void {
    $company = Company::factory()->create();
    $user = $this->createAdminUser($company);
    $user->syncRoles([]);
    $user->assignRole('user');

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $this->actingAs($user)
        ->withSession(['current_company_id' => $company->id])
        ->postJson(route('work_shifts.store'), [
            'name' => 'Reggel',
            'start_time' => '08:00',
            'end_time' => '16:00',
            'active' => true,
        ])
        ->assertForbidden();
});

it('validates required fields and equal shift times on store', function (): void {
    $company = Company::factory()->create();
    $user = $this->createAdminUser($company);

    $this->actingAs($user)
        ->withSession([
            'current_company_id' => $company->id,
            'current_tenant_group_id' => $company->tenant_group_id,
        ])
        ->postJson(route('work_shifts.store'), [
            'name' => '',
            'start_time' => '16:00',
            'end_time' => '16:00',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'end_time']);
});

it('stores shift with breaks and computes totals on server', function (): void {
    $company = Company::factory()->create();
    $user = $this->createAdminUser($company);
    $versioner = app(CacheVersionService::class);

    $beforeFetch = $versioner->get('work_shifts.fetch');
    $beforeSelector = $versioner->get('selectors.work_shifts');

    $this->actingAs($user)
        ->withSession([
            'current_company_id' => $company->id,
            'current_tenant_group_id' => $company->tenant_group_id,
        ])
        ->postJson(route('work_shifts.store'), [
            'name' => 'Délelőtt',
            'start_time' => '08:00',
            'end_time' => '16:30',
            'breaks' => [
                ['break_start_time' => '12:00', 'break_end_time' => '12:30'],
            ],
            'active' => true,
        ])
        ->assertCreated();

    $this->assertDatabaseHas('work_shifts', [
        'company_id' => $company->id,
        'name' => 'Délelőtt',
        'break_minutes' => 30,
        'work_time_minutes' => 480,
    ]);
    $this->assertDatabaseHas('work_shift_breaks', [
        'company_id' => $company->id,
        'break_start_time' => '12:00:00',
        'break_end_time' => '12:30:00',
        'break_minutes' => 30,
    ]);

    expect($versioner->get('work_shifts.fetch'))->toBeGreaterThan($beforeFetch);
    expect($versioner->get('selectors.work_shifts'))->toBeGreaterThan($beforeSelector);
});

it('stores overnight shift with in-window break correctly', function (): void {
    $company = Company::factory()->create();
    $user = $this->createAdminUser($company);

    $this->actingAs($user)
        ->withSession([
            'current_company_id' => $company->id,
            'current_tenant_group_id' => $company->tenant_group_id,
        ])
        ->postJson(route('work_shifts.store'), [
            'name' => 'Éjszakai',
            'start_time' => '22:00',
            'end_time' => '06:00',
            'breaks' => [
                ['break_start_time' => '02:00', 'break_end_time' => '02:15'],
            ],
            'active' => true,
        ])
        ->assertCreated();

    $this->assertDatabaseHas('work_shifts', [
        'company_id' => $company->id,
        'name' => 'Éjszakai',
        'break_minutes' => 15,
        'work_time_minutes' => 465,
    ]);
});

it('keeps work shift and break data tenant isolated on store/fetch', function (): void {
    $tenantA = TenantGroup::factory()->create();
    $tenantB = TenantGroup::factory()->create();
    $companyA = Company::factory()->create(['tenant_group_id' => $tenantA->id]);
    $companyB = Company::factory()->create(['tenant_group_id' => $tenantB->id]);
    $user = $this->createAdminUser($companyA);

    $tenantA->makeCurrent();

    $this->actingAs($user)
        ->withSession([
            'current_company_id' => $companyA->id,
            'current_tenant_group_id' => $tenantA->id,
        ])
        ->postJson(route('work_shifts.store'), [
            'name' => 'TenantA Shift',
            'start_time' => '08:00',
            'end_time' => '16:00',
            'breaks' => [
                ['break_start_time' => '12:00', 'break_end_time' => '12:30'],
            ],
            'active' => true,
        ])
        ->assertCreated();

    $this->actingAs($user)
        ->withSession([
            'current_company_id' => $companyB->id,
            'current_tenant_group_id' => $tenantA->id,
        ])
        ->getJson(route('work_shifts.fetch'))
        ->assertRedirect();
});
