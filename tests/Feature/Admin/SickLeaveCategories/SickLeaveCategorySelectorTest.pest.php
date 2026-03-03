<?php

declare(strict_types=1);

use App\Models\SickLeaveCategory;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('megtagadja a selector lekérést sick leave category jogosultság nélkül', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($company);
    $user->syncRoles([]);
    $user->syncPermissions([]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $this->actingAsUserInCompany($user, $company)
        ->getJson(route('admin.sick_leave_categories.selector'))
        ->assertRedirect();
});

it('only_active mellett csak az aktiv kategoriakat adja vissza sorrend szerint', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($company);

    SickLeaveCategory::factory()->create([
        'company_id' => $company->id,
        'name' => 'Masodik',
        'code' => 'masodik',
        'order_index' => 2,
        'active' => true,
    ]);
    SickLeaveCategory::factory()->create([
        'company_id' => $company->id,
        'name' => 'Elso',
        'code' => 'elso',
        'order_index' => 1,
        'active' => true,
    ]);
    SickLeaveCategory::factory()->create([
        'company_id' => $company->id,
        'name' => 'Inaktiv',
        'code' => 'inactive',
        'order_index' => 0,
        'active' => false,
    ]);

    $response = $this->actingAsUserInCompany($user, $company)
        ->getJson(route('admin.sick_leave_categories.selector', ['only_active' => 1]));

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'Elso')
        ->assertJsonPath('data.1.name', 'Masodik');
});

it('company scope szerint szuri a sick leave category selector adatokat', function (): void {
    [$tenantA, $companyA] = $this->createTenantWithCompany();
    [$tenantB, $companyB] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($companyA);

    SickLeaveCategory::factory()->create([
        'company_id' => $companyA->id,
        'name' => 'A ceg kategoria',
        'code' => 'slc_company_a',
        'active' => true,
    ]);
    SickLeaveCategory::factory()->create([
        'company_id' => $companyB->id,
        'name' => 'Masik ceg kategoria',
        'code' => 'slc_company_b',
        'active' => true,
    ]);

    $response = $this->actingAsUserInCompany($user, $companyA)
        ->getJson(route('admin.sick_leave_categories.selector', ['only_active' => 0]));

    expect(collect($response->json('data'))->pluck('name')->all())
        ->toContain('A ceg kategoria')
        ->not->toContain('Masik ceg kategoria');
});
