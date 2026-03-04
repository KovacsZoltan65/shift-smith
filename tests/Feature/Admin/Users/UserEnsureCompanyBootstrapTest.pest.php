<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\TenantGroup;
use App\Models\User;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('bootstraps company and tenant session for user store when actor has exactly one company', function (): void {
    $tenant = TenantGroup::factory()->create();
    $company = Company::factory()->create([
        'tenant_group_id' => (int) $tenant->id,
        'active' => true,
    ]);

    $admin = $this->createAdminUser($company);
    $admin->givePermissionTo('users.create');

    $this->actingAs($admin)
        ->postJson(route('users.store'), [
            'name' => 'Bootstrap User',
            'email' => 'bootstrap-user@example.test',
            'company_id' => (int) $company->id,
        ])
        ->assertOk()
        ->assertSessionHas('current_company_id', (int) $company->id)
        ->assertSessionHas('current_tenant_group_id', (int) $tenant->id);

    expect(User::query()->where('email', 'bootstrap-user@example.test')->exists())->toBeTrue();
});
