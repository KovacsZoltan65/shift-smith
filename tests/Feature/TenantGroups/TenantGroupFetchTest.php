<?php

declare(strict_types=1);

use App\Models\TenantGroup;

it('fetch supports search filter and sort for tenant groups', function (): void {
    TenantGroup::factory()->create([
        'name' => 'Beta Tenant',
        'code' => 'BETA_100',
        'status' => 'archived',
        'active' => false,
    ]);

    TenantGroup::factory()->create([
        'name' => 'Alpha Tenant',
        'code' => 'ALPHA_100',
        'status' => 'active',
        'active' => true,
    ]);

    TenantGroup::factory()->create([
        'name' => 'Alpha Sandbox',
        'code' => 'ALPHA_200',
        'status' => 'active',
        'active' => true,
    ]);

    $response = $this
        ->actingAs($this->user)
        ->get(route('hq.tenant_groups.fetch', [
            'search' => 'alpha',
            'active' => true,
            'status' => 'active',
            'sort_field' => 'name',
            'sort_direction' => 'asc',
            'per_page' => 10,
        ]));

    $response
        ->assertOk()
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('data.0.name', 'Alpha Sandbox')
        ->assertJsonPath('data.1.name', 'Alpha Tenant');
});
