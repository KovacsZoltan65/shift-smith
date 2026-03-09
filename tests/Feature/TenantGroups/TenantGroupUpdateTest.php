<?php

declare(strict_types=1);

use App\Models\TenantGroup;
use App\Services\Cache\CacheVersionService;

it('update works correctly', function (): void {
    $tenantGroup = TenantGroup::factory()->create([
        'name' => 'Legacy Tenant',
        'code' => 'LEGACY_001',
        'status' => 'draft',
        'active' => true,
    ]);

    $cacheVersions = app(CacheVersionService::class);
    $before = $cacheVersions->get('landlord:tenant-groups:list');

    $response = $this
        ->actingAs($this->user)
        ->putJson(route('hq.tenant_groups.update', $tenantGroup), [
            'name' => 'Updated Tenant',
            'code' => 'UPDATED_001',
            'status' => 'archived',
            'active' => false,
            'notes' => 'Archived safely',
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.name', 'Updated Tenant')
        ->assertJsonPath('data.code', 'UPDATED_001')
        ->assertJsonPath('data.active', false);

    $tenantGroup->refresh();

    expect($tenantGroup->name)->toBe('Updated Tenant');
    expect($tenantGroup->code)->toBe('UPDATED_001');
    expect((bool) $tenantGroup->active)->toBeFalse();
    expect($cacheVersions->get('landlord:tenant-groups:list'))->toBeGreaterThan($before);
});
