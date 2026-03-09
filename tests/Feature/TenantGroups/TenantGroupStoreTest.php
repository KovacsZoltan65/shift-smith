<?php

declare(strict_types=1);

use App\Models\TenantGroup;
use App\Services\Cache\CacheVersionService;

it('create validates required fields', function (): void {
    $response = $this
        ->actingAs($this->user)
        ->postJson(route('hq.tenant_groups.store'), []);

    $response
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'code', 'active']);
});

it('code must be unique', function (): void {
    TenantGroup::factory()->create(['code' => 'ACME_001']);

    $response = $this
        ->actingAs($this->user)
        ->postJson(route('hq.tenant_groups.store'), [
            'name' => 'New Tenant',
            'code' => 'ACME_001',
            'status' => 'active',
            'active' => true,
            'notes' => 'Duplicate code',
        ]);

    $response
        ->assertStatus(422)
        ->assertJsonValidationErrors(['code']);
});

it('store creates a tenant group and bumps landlord cache version', function (): void {
    $cacheVersions = app(CacheVersionService::class);
    $before = $cacheVersions->get('landlord:tenant-groups:list');

    $response = $this
        ->actingAs($this->user)
        ->postJson(route('hq.tenant_groups.store'), [
            'name' => 'Acme Holding',
            'code' => 'ACME_HOLDING',
            'status' => 'active',
            'active' => true,
            'notes' => 'Created from test',
        ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.code', 'ACME_HOLDING');

    expect(TenantGroup::query()->where('code', 'ACME_HOLDING')->exists())->toBeTrue();
    expect($cacheVersions->get('landlord:tenant-groups:list'))->toBeGreaterThan($before);
});
