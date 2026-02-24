<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\TenantGroup;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
    TenantGroup::forgetCurrent();

    Route::middleware(['web', 'auth', 'ensure.company'])
        ->get('/_test/tenancy/bootstrap-protected', function () {
            return response()->json(['ok' => true]);
        });
});

afterEach(function (): void {
    TenantGroup::forgetCurrent();
});

it('sets current_company_id and current_tenant_group_id in session on explicit company selection', function (): void {
    $tenant = TenantGroup::factory()->create();
    $company = Company::factory()->create([
        'tenant_group_id' => $tenant->id,
        'active' => true,
    ]);

    $user = $this->createAdminUser($company);

    $this->actingAs($user)
        ->post(route('company.select.store'), ['company_id' => $company->id])
        ->assertRedirect(route('dashboard', absolute: false))
        ->assertSessionHas('current_company_id', $company->id)
        ->assertSessionHas('current_tenant_group_id', $tenant->id);
});

it('auto-select flow with exactly one company sets both company and tenant session keys', function (): void {
    $tenant = TenantGroup::factory()->create();
    $company = Company::factory()->create([
        'tenant_group_id' => $tenant->id,
        'active' => true,
    ]);

    $user = $this->createAdminUser($company);

    $this->actingAs($user)
        ->get(route('company.select'))
        ->assertRedirect(route('dashboard', absolute: false))
        ->assertSessionHas('current_company_id', $company->id)
        ->assertSessionHas('current_tenant_group_id', $tenant->id);
});

it('drifted company and tenant in session are cleared and redirected to company.select', function (): void {
    $tenantOne = TenantGroup::factory()->create();
    $tenantTwo = TenantGroup::factory()->create();

    $companyTenantOne = Company::factory()->create([
        'tenant_group_id' => $tenantOne->id,
        'active' => true,
    ]);
    $companyTenantTwo = Company::factory()->create([
        'tenant_group_id' => $tenantTwo->id,
        'active' => true,
    ]);

    $user = $this->createAdminUser($companyTenantOne);
    $user->companies()->syncWithoutDetaching([$companyTenantTwo->id]);

    $this->actingAs($user)
        ->withSession([
            'current_tenant_group_id' => $tenantOne->id,
            'current_company_id' => $companyTenantTwo->id,
        ])
        ->get('/_test/tenancy/bootstrap-protected')
        ->assertRedirect(route('company.select'))
        ->assertSessionMissing('current_company_id')
        ->assertSessionMissing('current_tenant_group_id');
});
