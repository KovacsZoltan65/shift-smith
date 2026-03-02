<?php

declare(strict_types=1);

use App\Models\CompanySetting;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('nem enged hozzáférést más company rekordjához', function (): void {
    [$tenantA, $companyA] = $this->createTenantWithCompany();
    [$tenantB, $companyB] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($companyA);

    $foreign = CompanySetting::query()->create([
        'company_id' => $companyB->id,
        'key' => 'leave.foreign',
        'value' => 1,
        'type' => 'int',
        'group' => 'leave',
    ]);

    $this->actingAsUserInCompany($user, $companyA)
        ->putJson(route('admin.company_settings.update', $foreign->id), [
            'key' => 'leave.foreign',
            'type' => 'int',
            'group' => 'leave',
            'value' => 9,
        ])
        ->assertNotFound();
});

it('frissíti a selected company rekordját', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($company);
    $setting = CompanySetting::query()->create([
        'company_id' => $company->id,
        'key' => 'leave.update_me',
        'value' => 1,
        'type' => 'int',
        'group' => 'leave',
    ]);

    $this->actingAsUserInCompany($user, $company)
        ->putJson(route('admin.company_settings.update', $setting->id), [
            'key' => 'leave.update_me',
            'type' => 'int',
            'group' => 'leave',
            'value' => 15,
        ])
        ->assertOk()
        ->assertJsonPath('data.value', 15);
});
