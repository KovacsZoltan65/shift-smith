<?php

declare(strict_types=1);

use App\Models\SickLeaveCategory;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('soft delete-olja a sajat company sick leave category rekordjat', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($company);
    $category = SickLeaveCategory::factory()->create([
        'company_id' => $company->id,
    ]);

    $this->actingAsUserInCompany($user, $company)
        ->deleteJson(route('admin.sick_leave_categories.destroy', $category->id))
        ->assertOk()
        ->assertJsonPath('deleted', true);

    $this->assertSoftDeleted('sick_leave_categories', [
        'id' => $category->id,
        'company_id' => $company->id,
    ]);
});

it('nem torli masik company rekordjat', function (): void {
    [$tenantA, $companyA] = $this->createTenantWithCompany();
    [$tenantB, $companyB] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($companyA);
    $foreign = SickLeaveCategory::factory()->create([
        'company_id' => $companyB->id,
    ]);

    $this->actingAsUserInCompany($user, $companyA)
        ->deleteJson(route('admin.sick_leave_categories.destroy', $foreign->id))
        ->assertNotFound();
});
