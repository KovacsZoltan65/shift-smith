<?php

declare(strict_types=1);

use App\Models\SickLeaveCategory;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('frissiti a sajat company sick leave category rekordjat', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($company);
    $category = SickLeaveCategory::factory()->create([
        'company_id' => $company->id,
        'code' => 'sajat_betegseg',
        'name' => 'Sajat betegseg',
        'description' => 'Eredeti leiras',
        'active' => true,
        'order_index' => 5,
    ]);

    $this->actingAsUserInCompany($user, $company)
        ->putJson(route('admin.sick_leave_categories.update', $category->id), [
            'code' => 'sajat_betegseg',
            'name' => 'Frissitett betegseg',
            'description' => 'Frissitett leiras',
            'active' => false,
            'order_index' => 7,
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Frissitett betegseg')
        ->assertJsonPath('data.description', 'Frissitett leiras')
        ->assertJsonPath('data.active', false)
        ->assertJsonPath('data.order_index', 7);
});

it('nem frissiti masik company rekordjat', function (): void {
    [$tenantA, $companyA] = $this->createTenantWithCompany();
    [$tenantB, $companyB] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($companyA);
    $foreign = SickLeaveCategory::factory()->create([
        'company_id' => $companyB->id,
    ]);

    $this->actingAsUserInCompany($user, $companyA)
        ->putJson(route('admin.sick_leave_categories.update', $foreign->id), [
            'name' => 'Tiltott update',
            'description' => null,
            'active' => true,
            'order_index' => 0,
        ])
        ->assertNotFound();
});

it('code nem modosithato update kozben', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($company);
    $category = SickLeaveCategory::factory()->create([
        'company_id' => $company->id,
        'code' => 'sajat_betegseg',
    ]);

    $this->actingAsUserInCompany($user, $company)
        ->putJson(route('admin.sick_leave_categories.update', $category->id), [
            'code' => 'masik_kod',
            'name' => 'Sajat betegseg',
            'description' => null,
            'active' => true,
            'order_index' => 0,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['code']);
});
