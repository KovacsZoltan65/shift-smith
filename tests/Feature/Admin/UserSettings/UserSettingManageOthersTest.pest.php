<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\UserSetting;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('manageOthers jogosultsággal lekérheti ugyanazon company más user rekordjait', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $admin = $this->createAdminUser($company);
    $other = User::factory()->create(['email_verified_at' => now()]);
    $other->companies()->syncWithoutDetaching([$company->id]);

    UserSetting::query()->create([
        'company_id' => $company->id,
        'user_id' => $other->id,
        'key' => 'other.theme',
        'value' => 'dark',
        'type' => 'string',
        'group' => 'ui',
    ]);

    $response = $this->actingAsUserInCompany($admin, $company)
        ->getJson(route('admin.user_settings.fetch', ['user_id' => $other->id]));

    $response->assertOk();
    expect($response->json('meta.total'))->toBe(1);
    expect($response->json('items.0.user_id'))->toBe($other->id);
});

it('manageOthers nélkül tiltja más user scope-ját', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->companies()->syncWithoutDetaching([$company->id]);
    $user->assignRole('user');

    $other = User::factory()->create(['email_verified_at' => now()]);
    $other->companies()->syncWithoutDetaching([$company->id]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $this->actingAsUserInCompany($user, $company)
        ->getJson(route('admin.user_settings.fetch', ['user_id' => $other->id]))
        ->assertForbidden();
});
