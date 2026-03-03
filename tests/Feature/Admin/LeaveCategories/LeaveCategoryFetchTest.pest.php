<?php

declare(strict_types=1);

use App\Models\LeaveCategory;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('csak az aktualis company leave category rekordjait adja vissza', function (): void {
    [$tenantA, $companyA] = $this->createTenantWithCompany();
    [$tenantB, $companyB] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($companyA);

    $visible = LeaveCategory::factory()->create([
        'company_id' => $companyA->id,
        'code' => 'leave',
        'name' => 'Szabadsag',
    ]);

    LeaveCategory::factory()->create([
        'company_id' => $companyB->id,
        'code' => 'foreign',
        'name' => 'Masik tenant',
    ]);

    $this->actingAsUserInCompany($user, $companyA)
        ->getJson(route('admin.leave_categories.fetch'))
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('items.0.id', $visible->id)
        ->assertJsonMissing(['code' => 'foreign']);
});

it('permission nelkul 403-at ad a fetch endpointre', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($company);
    $user->syncRoles([]);
    $user->syncPermissions(['companies.view']);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $this->actingAsUserInCompany($user, $company)
        ->getJson(route('admin.leave_categories.fetch'))
        ->assertForbidden();
});
