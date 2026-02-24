<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\TenantGroup;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
    TenantGroup::forgetCurrent();

    Route::middleware(['web', 'auth', 'verified', 'ensure.company'])
        ->get('/_test/tenancy/company-selection-drift', function () {
            return response()->json(['ok' => true]);
        });
});

afterEach(function (): void {
    TenantGroup::forgetCurrent();
});

it('resets current company and redirects when company belongs to another tenant group', function (): void {
    $tenantOne = TenantGroup::factory()->create();
    $tenantTwo = TenantGroup::factory()->create();

    $companyInTenantOne = Company::factory()->create([
        'tenant_group_id' => $tenantOne->id,
        'active' => true,
    ]);
    $companyInTenantTwo = Company::factory()->create([
        'tenant_group_id' => $tenantTwo->id,
        'active' => true,
    ]);

    $user = $this->createAdminUser($companyInTenantOne);
    $user->companies()->syncWithoutDetaching([$companyInTenantTwo->id]);

    $this->actingAs($user)
        ->withSession([
            'current_tenant_group_id' => $tenantOne->id,
            'current_company_id' => $companyInTenantTwo->id,
        ])
        ->get('/_test/tenancy/company-selection-drift')
        ->assertRedirect(route('company.select'))
        ->assertSessionMissing('current_company_id');
});

it('allows request when current company belongs to current tenant group', function (): void {
    $tenant = TenantGroup::factory()->create();
    $company = Company::factory()->create([
        'tenant_group_id' => $tenant->id,
        'active' => true,
    ]);

    $user = $this->createAdminUser($company);

    $this->actingAs($user)
        ->withSession([
            'current_tenant_group_id' => $tenant->id,
            'current_company_id' => $company->id,
        ])
        ->getJson('/_test/tenancy/company-selection-drift')
        ->assertOk()
        ->assertJsonPath('ok', true);
});

it('redirects to company selection when tenant is set but no current company is selected', function (): void {
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
    $user->companies()->syncWithoutDetaching([$companyB->id]);

    $this->actingAs($user)
        ->withSession([
            'current_tenant_group_id' => $tenant->id,
        ])
        ->get('/_test/tenancy/company-selection-drift')
        ->assertRedirect(route('company.select'));
});
