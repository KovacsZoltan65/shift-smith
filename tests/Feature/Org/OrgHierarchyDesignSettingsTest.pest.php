<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\TenantGroup;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('returns 403 for design settings save without hierarchy permission', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $tenant->makeCurrent();

    /** @var User $user */
    $user = User::factory()->create();
    $user->assignRole('user');
    $user->companies()->syncWithoutDetaching([$company->id]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $this->actingAsUserInCompany($user, $company)
        ->postJson(route('org.hierarchy.design_settings.save'), [
            'company_id' => $company->id,
            'view_mode' => 'network',
            'density' => 'compact',
            'show_position' => true,
        ])
        ->assertForbidden();
});

it('saves hierarchy design settings in user scope and returns them on hierarchy page', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $tenant->makeCurrent();

    $admin = $this->createAdminUser($company);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $admin->refresh();

    $this->actingAsUserInCompany($admin, $company)
        ->postJson(route('org.hierarchy.design_settings.save'), [
            'company_id' => $company->id,
            'view_mode' => 'network',
            'density' => 'compact',
            'show_position' => false,
        ])
        ->assertOk()
        ->assertJsonPath('data.view_mode', 'network')
        ->assertJsonPath('data.density', 'compact')
        ->assertJsonPath('data.show_position', false);

    $this->assertDatabaseHas('user_settings', [
        'company_id' => $company->id,
        'user_id' => $admin->id,
        'key' => 'ui.hierarchy.view_mode',
        'value' => '"network"',
    ]);
    $this->assertDatabaseHas('user_settings', [
        'company_id' => $company->id,
        'user_id' => $admin->id,
        'key' => 'ui.hierarchy.density',
        'value' => '"compact"',
    ]);
    $this->assertDatabaseHas('user_settings', [
        'company_id' => $company->id,
        'user_id' => $admin->id,
        'key' => 'ui.hierarchy.show_position',
        'value' => 'false',
    ]);

    $this->actingAsUserInCompany($admin, $company)
        ->get(route('org.hierarchy.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('HR/Hierarchy/Index')
            ->where('ui_settings.view_mode', 'network')
            ->where('ui_settings.density', 'compact')
            ->where('ui_settings.show_position', false)
        );
});

it('enforces company scope and tenant isolation for design settings save', function (): void {
    $tenantA = TenantGroup::factory()->create();
    $tenantB = TenantGroup::factory()->create();
    $companyA = Company::factory()->create(['tenant_group_id' => $tenantA->id]);
    $companyB = Company::factory()->create(['tenant_group_id' => $tenantB->id]);
    $tenantA->makeCurrent();

    $admin = $this->createAdminUser($companyA);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $admin->refresh();

    $this->actingAsUserInCompany($admin, $companyA)
        ->postJson(route('org.hierarchy.design_settings.save'), [
            'company_id' => $companyB->id,
            'view_mode' => 'network',
            'density' => 'compact',
            'show_position' => true,
        ])
        ->assertForbidden();
});

