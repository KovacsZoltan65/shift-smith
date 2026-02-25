<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\TenantGroup;
use App\Models\WorkSchedule;
use App\Services\Cache\CacheNamespaces;
use App\Models\User;
use App\Services\Cache\CacheVersionService;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('denies work schedule creation if user lacks permission', function (): void {
    /** @var User $user */
    $user = User::factory()->create();
    $user->assignRole('user');

    $tenant = TenantGroup::factory()->create();
    $company = Company::factory()->create(['tenant_group_id' => $tenant->id]);

    $this
        ->actingAsUserInCompany($user, $company)
        ->postJson(route('work_schedules.store'), [
            'company_id' => $company->id,
            'name' => 'Nope',
            'date_from' => '2026-02-01',
            'date_to' => '2026-02-10',
            'status' => 'draft',
        ])
        ->assertForbidden();

    $this->assertDatabaseMissing('work_schedules', ['name' => 'Nope']);
});

it('validates required fields on store', function (): void {
    $user = $this->createAdminUser();
    [, $company] = $this->createTenantWithCompany();

    $this
        ->actingAsUserInCompany($user, $company)
        ->postJson(route('work_schedules.store'), [
            'company_id' => null,
            'name' => '',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['company_id', 'name', 'date_from', 'date_to', 'status']);
});

it('allows admin to store a work schedule and bumps cache versions', function (): void {
    [, $company] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($company);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $versioner = app(CacheVersionService::class);
    $tenantNamespace = CacheNamespaces::tenantWorkSchedules((int) $company->tenant_group_id);
    $companyNamespace = "company:{$company->id}:work_schedules";
    $tenantBefore = $versioner->get($tenantNamespace);
    $companyBefore = $versioner->get($companyNamespace);

    $payload = WorkSchedule::factory()->make([
        'company_id' => $company->id,
        'status' => 'draft',
    ])->only(['company_id', 'name', 'date_from', 'date_to', 'status']);

    $payload['date_from'] = \Illuminate\Support\Carbon::parse((string) $payload['date_from'])->format('Y-m-d');
    $payload['date_to'] = \Illuminate\Support\Carbon::parse((string) $payload['date_to'])->format('Y-m-d');

    $this
        ->actingAsUserInCompany($user, $company)
        ->postJson(route('work_schedules.store'), $payload)
        ->assertCreated();

    $this->assertDatabaseHas('work_schedules', [
        'company_id' => $company->id,
        'name' => $payload['name'],
        'status' => 'draft',
    ]);

    expect($versioner->get($tenantNamespace))->toBeGreaterThan($tenantBefore);
    expect($versioner->get($companyNamespace))->toBeGreaterThan($companyBefore);
});
