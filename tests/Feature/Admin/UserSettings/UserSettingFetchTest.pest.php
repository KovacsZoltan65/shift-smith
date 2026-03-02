<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\UserSetting;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('self mode-ban csak a saját rekordokat listázza', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($company);
    $other = User::factory()->create(['email_verified_at' => now()]);
    $other->companies()->syncWithoutDetaching([$company->id]);

    UserSetting::query()->create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'key' => 'self.only',
        'value' => 'mine',
        'type' => 'string',
        'group' => 'user',
    ]);
    UserSetting::query()->create([
        'company_id' => $company->id,
        'user_id' => $other->id,
        'key' => 'self.only',
        'value' => 'other',
        'type' => 'string',
        'group' => 'user',
    ]);

    $response = $this->actingAsUserInCompany($user, $company)
        ->getJson(route('admin.user_settings.fetch'));

    $response->assertOk();
    expect($response->json('meta.total'))->toBe(1);
    expect($response->json('items.0.value'))->toBe('mine');
});
