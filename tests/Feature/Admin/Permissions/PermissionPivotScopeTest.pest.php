<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\TenantGroup;
use App\Models\User;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
    TenantGroup::forgetCurrent();
});

afterEach(function (): void {
    TenantGroup::forgetCurrent();
});

it('prevents tenant A admin from mutating tenant B user permission pivot via delete flow', function (): void {
    $tenantA = TenantGroup::factory()->create();
    $tenantB = TenantGroup::factory()->create();

    $companyA = Company::factory()->create(['tenant_group_id' => $tenantA->id]);
    $companyB = Company::factory()->create(['tenant_group_id' => $tenantB->id]);

    $adminA = $this->createAdminUser($companyA);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $adminA->refresh();

    /** @var User $targetUser */
    $targetUser = User::factory()->create();
    $targetUser->companies()->syncWithoutDetaching([$companyB->id]);
    $targetUser->givePermissionTo('companies.viewAny');

    $this->actingAs($adminA)
        ->deleteJson(route('users.destroy', ['id' => $targetUser->id]))
        ->assertNotFound();

    $this->assertDatabaseHas('users', ['id' => $targetUser->id]);
});

it('allows superadmin in landlord mode to mutate cross-tenant user permission pivot via delete flow', function (): void {
    $tenantA = TenantGroup::factory()->create();
    $tenantB = TenantGroup::factory()->create();

    $companyA = Company::factory()->create(['tenant_group_id' => $tenantA->id]);
    $companyB = Company::factory()->create(['tenant_group_id' => $tenantB->id]);

    $superadmin = $this->createSuperadminUser();
    $superadmin->companies()->syncWithoutDetaching([$companyA->id]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $superadmin->refresh();

    /** @var User $targetUser */
    $targetUser = User::factory()->create();
    $targetUser->companies()->syncWithoutDetaching([$companyB->id]);
    $targetUser->givePermissionTo('companies.viewAny');

    TenantGroup::forgetCurrent();

    $this->actingAs($superadmin)
        ->deleteJson(route('users.destroy', ['id' => $targetUser->id]))
        ->assertOk();

    $this->assertDatabaseMissing('users', ['id' => $targetUser->id]);
});

