<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\UserSetting;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('self mode-ban nem fér hozzá más user rekordjához ugyanabban a companyban sem', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($company);
    $other = User::factory()->create(['email_verified_at' => now()]);
    $other->companies()->syncWithoutDetaching([$company->id]);

    $setting = UserSetting::query()->create([
        'company_id' => $company->id,
        'user_id' => $other->id,
        'key' => 'other.hidden',
        'value' => 'x',
        'type' => 'string',
        'group' => 'user',
    ]);

    $this->actingAsUserInCompany($user, $company)
        ->getJson(route('admin.user_settings.show', $setting->id))
        ->assertNotFound();
});

it('más company és más tenant rekordjai nem érhetők el', function (): void {
    [$tenantA, $companyA] = $this->createTenantWithCompany();
    [$tenantB, $companyB] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($companyA);

    $setting = UserSetting::query()->create([
        'company_id' => $companyB->id,
        'user_id' => $user->id,
        'key' => 'foreign.scope',
        'value' => 'x',
        'type' => 'string',
        'group' => 'user',
    ]);

    $this->actingAsUserInCompany($user, $companyA)
        ->getJson(route('admin.user_settings.show', $setting->id))
        ->assertNotFound();
});
