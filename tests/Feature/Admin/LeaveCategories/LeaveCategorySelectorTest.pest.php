<?php

declare(strict_types=1);

use App\Models\LeaveCategory;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('megtagadja a selector lekerest jogosultsag nelkul', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($company);
    $user->syncRoles([]);
    $user->syncPermissions([]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $this->actingAsUserInCompany($user, $company)
        ->getJson(route('admin.leave_categories.selector'))
        ->assertRedirect();
});

it('csak aktiv rekordokat ad vissza sorrend szerint', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($company);

    LeaveCategory::factory()->create([
        'company_id' => $company->id,
        'code' => 'paid_absence',
        'name' => 'Fizetett tavollet',
        'order_index' => 30,
        'active' => true,
    ]);
    LeaveCategory::factory()->create([
        'company_id' => $company->id,
        'code' => 'leave',
        'name' => 'Szabadsag',
        'order_index' => 10,
        'active' => true,
    ]);
    LeaveCategory::factory()->create([
        'company_id' => $company->id,
        'code' => 'inactive',
        'name' => 'Inaktiv',
        'order_index' => 0,
        'active' => false,
    ]);

    $this->actingAsUserInCompany($user, $company)
        ->getJson(route('admin.leave_categories.selector'))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.code', 'leave')
        ->assertJsonPath('data.1.code', 'paid_absence');
});
