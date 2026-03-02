<?php

declare(strict_types=1);

use App\Models\CompanySetting;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('törli a current company rekordját', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($company);
    $setting = CompanySetting::query()->create([
        'company_id' => $company->id,
        'key' => 'leave.delete_me',
        'value' => 1,
        'type' => 'int',
        'group' => 'leave',
    ]);

    $this->actingAsUserInCompany($user, $company)
        ->deleteJson(route('admin.company_settings.destroy', $setting->id))
        ->assertOk()
        ->assertJsonPath('deleted', true);

    $this->assertSoftDeleted('company_settings', ['id' => $setting->id]);
});
