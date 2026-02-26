<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\TenantGroup;
use App\Models\User;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
    TenantGroup::forgetCurrent();
});

afterEach(function (): void {
    TenantGroup::forgetCurrent();
});

it('returns 403 on dashboard when no selected company exists in session', function (): void {
    /** @var User $user */
    $user = User::factory()->create();
    $user->assignRole('admin');

    $this->actingAs($user)
        ->withSession([])
        ->get(route('dashboard'))
        ->assertForbidden();
});

it('initializes tenant context from selected company on dashboard', function (): void {
    $tenantGroup = TenantGroup::factory()->create();
    $company = Company::factory()->create([
        'tenant_group_id' => $tenantGroup->id,
        'active' => true,
    ]);

    $user = $this->createAdminUser($company);
    TenantGroup::forgetCurrent();

    $this->actingAs($user)
        ->withSession([
            'current_company_id' => (int) $company->id,
        ])
        ->get(route('dashboard'))
        ->assertOk();

    expect((int) (TenantGroup::current()?->id ?? 0))->toBe((int) $tenantGroup->id);
});

it('returns 403 when selected company belongs to another tenant and user is not assigned', function (): void {
    $tenantGroupOne = TenantGroup::factory()->create();
    $tenantGroupTwo = TenantGroup::factory()->create();

    $companyA = Company::factory()->create([
        'tenant_group_id' => $tenantGroupOne->id,
        'active' => true,
    ]);
    $companyB = Company::factory()->create([
        'tenant_group_id' => $tenantGroupTwo->id,
        'active' => true,
    ]);

    $user = $this->createAdminUser($companyA);
    TenantGroup::forgetCurrent();

    $this->actingAs($user)
        ->withSession([
            'selected_company_id' => (int) $companyB->id,
        ])
        ->get(route('dashboard'))
        ->assertRedirect(route('company.select', absolute: false));
});
