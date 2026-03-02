<?php

declare(strict_types=1);

use App\Models\UserSetting;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('törli a saját scope user settinget', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($company);
    $setting = UserSetting::query()->create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'key' => 'user.remove',
        'value' => true,
        'type' => 'bool',
        'group' => 'ui',
    ]);

    $this->actingAsUserInCompany($user, $company)
        ->deleteJson(route('admin.user_settings.destroy', $setting->id))
        ->assertOk()
        ->assertJsonPath('deleted', true);
});
