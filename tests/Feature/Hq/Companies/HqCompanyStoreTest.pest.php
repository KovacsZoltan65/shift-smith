<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\TenantGroup;
use App\Models\User;
use App\Services\Cache\CacheVersionService;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
    TenantGroup::forgetCurrent();
});

afterEach(function (): void {
    TenantGroup::forgetCurrent();
});

it('allows superadmin to create a company inside an existing tenant group', function (): void {
    $superadmin = $this->createSuperadminUser();
    $tenantGroup = TenantGroup::factory()->create();
    $versioner = app(CacheVersionService::class);

    $hqFetchBefore = $versioner->get('hq.companies.fetch');

    $payload = [
        'tenant_group_id' => $tenantGroup->id,
        'name' => 'HQ Created Company',
        'email' => fake()->unique()->userName().'@gmail.com',
        'address' => '1111 Budapest, HQ utca 1.',
        'phone' => '+3611111111',
        'active' => true,
    ];

    $this->actingAs($superadmin)
        ->postJson(route('hq.companies.store'), $payload)
        ->assertCreated()
        ->assertJsonPath('message', __('companies.hq.messages.created_success'));

    $this->assertDatabaseHas('companies', [
        'name' => 'HQ Created Company',
        'tenant_group_id' => $tenantGroup->id,
    ]);

    expect($versioner->get('hq.companies.fetch'))->toBeGreaterThan($hqFetchBefore);
});

it('validates tenant_group_id on hq company create', function (): void {
    $superadmin = $this->createSuperadminUser();

    $payload = [
        'tenant_group_id' => 999999,
        'name' => 'HQ Invalid Tenant Group',
        'email' => fake()->unique()->userName().'@gmail.com',
        'address' => '1111 Budapest, Invalid utca 2.',
        'phone' => '+3622222222',
        'active' => true,
    ];

    $this->actingAs($superadmin)
        ->postJson(route('hq.companies.store'), $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['tenant_group_id']);
});

it('forbids hq company create without permission', function (): void {
    $admin = $this->createAdminUser();
    $tenantGroup = TenantGroup::factory()->create();

    $payload = [
        'tenant_group_id' => $tenantGroup->id,
        'name' => 'Forbidden HQ Company',
        'email' => fake()->unique()->userName().'@gmail.com',
        'address' => '1111 Budapest, Forbidden utca 3.',
        'phone' => '+3633333333',
        'active' => true,
    ];

    $this->actingAs($admin)
        ->postJson(route('hq.companies.store'), $payload)
        ->assertForbidden();
});

it('denies tenant user access to hq company create endpoint', function (): void {
    $tenantUser = User::factory()->create();
    $tenantUser->assignRole('user');
    $tenantGroup = TenantGroup::factory()->create();

    $payload = [
        'tenant_group_id' => $tenantGroup->id,
        'name' => 'Tenant User Forbidden HQ Company',
        'email' => fake()->unique()->userName().'@gmail.com',
        'address' => '1111 Budapest, Tenant utca 4.',
        'phone' => '+3644444444',
        'active' => true,
    ];

    $this->actingAs($tenantUser)
        ->postJson(route('hq.companies.store'), $payload)
        ->assertForbidden();
});
