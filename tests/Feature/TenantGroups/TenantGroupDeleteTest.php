<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\TenantGroup;
use App\Services\Cache\CacheVersionService;

it('delete is blocked when tenant group still has companies', function (): void {
    $tenantGroup = TenantGroup::factory()->create();
    Company::factory()->create([
        'tenant_group_id' => (int) $tenantGroup->id,
        'active' => true,
    ]);

    $response = $this
        ->actingAs($this->user)
        ->deleteJson(route('hq.tenant_groups.destroy', $tenantGroup));

    $response
        ->assertStatus(409)
        ->assertJsonPath('meta.impact.company_count', 1);

    expect($tenantGroup->fresh()?->deleted_at)->toBeNull();
});

it('delete succeeds when tenant group has no companies and bumps cache version', function (): void {
    $tenantGroup = TenantGroup::factory()->create();
    $cacheVersions = app(CacheVersionService::class);
    $before = $cacheVersions->get('landlord:tenant-groups:list');

    $response = $this
        ->actingAs($this->user)
        ->deleteJson(route('hq.tenant_groups.destroy', $tenantGroup));

    $response
        ->assertOk()
        ->assertJsonPath('deleted', true);

    $deletedTenantGroup = TenantGroup::query()->withTrashed()->find($tenantGroup->id);

    expect($deletedTenantGroup)->not->toBeNull();
    expect($deletedTenantGroup?->deleted_at)->not->toBeNull();
    expect($cacheVersions->get('landlord:tenant-groups:list'))->toBeGreaterThan($before);
});
