<?php

declare(strict_types=1);

use App\Models\TenantGroup;

it('authorized HQ user can show a tenant group', function (): void {
    $tenantGroup = TenantGroup::factory()->create([
        'name' => 'Show Tenant',
        'code' => 'SHOW_001',
        'status' => 'draft',
    ]);

    $response = $this
        ->actingAs($this->user)
        ->get(route('hq.tenant_groups.show', $tenantGroup));

    $response
        ->assertOk()
        ->assertJsonPath('data.id', (int) $tenantGroup->id)
        ->assertJsonPath('data.code', 'SHOW_001');
});
