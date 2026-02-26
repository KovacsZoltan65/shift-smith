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

it('returns 403 on dashboard when user has no assigned company', function (): void {
    /** @var User $user */
    $user = User::factory()->create();
    $user->assignRole('admin');

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertForbidden();
});

it('auto-selects single company and opens dashboard', function (): void {
    $tenant = TenantGroup::factory()->create();
    $company = Company::factory()->create([
        'tenant_group_id' => $tenant->id,
        'active' => true,
    ]);

    $user = $this->createAdminUser($company);

    $this->actingAs($user)
        ->withSession([])
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSessionHas('selected_company_id', (int) $company->id);
});

it('redirects to company selector when user has multiple companies and none selected', function (): void {
    $tenant = TenantGroup::factory()->create();
    $companyA = Company::factory()->create([
        'tenant_group_id' => $tenant->id,
        'active' => true,
    ]);
    $companyB = Company::factory()->create([
        'tenant_group_id' => $tenant->id,
        'active' => true,
    ]);

    $user = $this->createAdminUser($companyA);
    $user->companies()->syncWithoutDetaching([(int) $companyB->id]);

    $this->actingAs($user)
        ->withSession([])
        ->get(route('dashboard'))
        ->assertRedirect(route('company.select', absolute: false));
});

it('forbids selecting company from another tenant when user is not assigned', function (): void {
    $tenantA = TenantGroup::factory()->create();
    $tenantB = TenantGroup::factory()->create();

    $companyA = Company::factory()->create([
        'tenant_group_id' => $tenantA->id,
        'active' => true,
    ]);
    $companyB = Company::factory()->create([
        'tenant_group_id' => $tenantB->id,
        'active' => true,
    ]);

    $user = $this->createAdminUser($companyA);

    $this->actingAs($user)
        ->post(route('company.select.store'), ['company_id' => (int) $companyB->id])
        ->assertForbidden();
});

