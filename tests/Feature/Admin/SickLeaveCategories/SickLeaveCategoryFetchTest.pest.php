<?php

declare(strict_types=1);

use App\Models\SickLeaveCategory;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('csak az aktualis company sick leave category rekordjait adja vissza', function (): void {
    [$tenantA, $companyA] = $this->createTenantWithCompany();
    [$tenantB, $companyB] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($companyA);

    $visible = SickLeaveCategory::factory()->create([
        'company_id' => $companyA->id,
        'code' => 'sajat_betegseg',
        'name' => 'Sajat betegseg',
    ]);

    SickLeaveCategory::factory()->create([
        'company_id' => $companyB->id,
        'code' => 'foreign',
        'name' => 'Masik tenant',
    ]);

    $this->actingAsUserInCompany($user, $companyA)
        ->getJson(route('admin.sick_leave_categories.fetch'))
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('items.0.id', $visible->id)
        ->assertJsonPath('items.0.company_id', $companyA->id)
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
        ->getJson(route('admin.sick_leave_categories.fetch'))
        ->assertForbidden();
});

it('show endpointen masik company rekordja nem latszik', function (): void {
    [$tenantA, $companyA] = $this->createTenantWithCompany();
    [$tenantB, $companyB] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($companyA);

    $foreign = SickLeaveCategory::factory()->create([
        'company_id' => $companyB->id,
    ]);

    $this->actingAsUserInCompany($user, $companyA)
        ->getJson(route('admin.sick_leave_categories.show', $foreign->id))
        ->assertNotFound();
});
