<?php

declare(strict_types=1);

use App\Models\LeaveCategory;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('frissiti a sajat company leave category rekordjat', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($company);
    $category = LeaveCategory::factory()->create([
        'company_id' => $company->id,
        'code' => 'leave',
        'name' => 'Szabadsag',
    ]);

    $this->actingAsUserInCompany($user, $company)
        ->putJson(route('admin.leave_categories.update', $category->id), [
            'code' => 'leave',
            'name' => 'Frissitett kategoria',
            'description' => 'Frissitett leiras',
            'active' => false,
            'order_index' => 11,
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Frissitett kategoria')
        ->assertJsonPath('data.active', false);
});

it('code nem modosithato update kozben', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($company);
    $category = LeaveCategory::factory()->create([
        'company_id' => $company->id,
        'code' => 'leave',
    ]);

    $this->actingAsUserInCompany($user, $company)
        ->putJson(route('admin.leave_categories.update', $category->id), [
            'code' => 'masik_kod',
            'name' => 'Szabadsag',
            'description' => null,
            'active' => true,
            'order_index' => 10,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['code']);
});
