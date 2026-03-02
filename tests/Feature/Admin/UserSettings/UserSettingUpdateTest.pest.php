<?php

declare(strict_types=1);

use App\Models\UserSetting;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('frissíti a saját scope user settinget', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($company);
    $setting = UserSetting::query()->create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'key' => 'user.locale',
        'value' => 'hu',
        'type' => 'string',
        'group' => 'ui',
    ]);

    $this->actingAsUserInCompany($user, $company)
        ->putJson(route('admin.user_settings.update', $setting->id), [
            'key' => 'user.locale',
            'type' => 'string',
            'group' => 'ui',
            'value' => 'en',
        ])
        ->assertOk()
        ->assertJsonPath('data.value', 'en');
});
