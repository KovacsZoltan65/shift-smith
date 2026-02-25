<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\TenantGroup;
use App\Services\Cache\CacheVersionService;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
    TenantGroup::forgetCurrent();
});

afterEach(function (): void {
    TenantGroup::forgetCurrent();
});

it('forbids non-superadmin from hq companies fetch', function (): void {
    $user = $this->createAdminUser();

    $this->actingAs($user)
        ->getJson(route('hq.companies.fetch'))
        ->assertForbidden();
});

it('allows superadmin to see all companies across tenant groups', function (): void {
    $superadmin = $this->createSuperadminUser();

    $tenantOne = TenantGroup::factory()->create();
    $tenantTwo = TenantGroup::factory()->create();

    Company::factory()->count(2)->create(['tenant_group_id' => $tenantOne->id]);
    Company::factory()->count(3)->create(['tenant_group_id' => $tenantTwo->id]);

    $this->actingAs($superadmin)
        ->withSession(['current_tenant_group_id' => $tenantOne->id])
        ->getJson(route('hq.companies.fetch', ['page' => 1, 'per_page' => 10]))
        ->assertOk()
        ->assertJsonPath('meta.total', 5);
});

it('uses landlord cache namespace for hq companies fetch version key', function (): void {
    $superadmin = $this->createSuperadminUser();
    $versioner = app(CacheVersionService::class);

    $tenant = TenantGroup::factory()->create();
    $tenant->makeCurrent();
    $tenantBefore = $versioner->get('hq.companies.fetch');

    TenantGroup::forgetCurrent();
    $landlordBefore = $versioner->get('hq.companies.fetch');
    $versioner->bump('hq.companies.fetch');
    $landlordAfter = $versioner->get('hq.companies.fetch');

    expect($landlordAfter)->toBeGreaterThan($landlordBefore);

    $tenant->makeCurrent();
    $tenantAfter = $versioner->get('hq.companies.fetch');
    expect($tenantAfter)->toBe($tenantBefore);

    $this->actingAs($superadmin)
        ->getJson(route('hq.companies.fetch'))
        ->assertOk();
});
