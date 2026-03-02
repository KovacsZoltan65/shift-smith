<?php

declare(strict_types=1);

use App\Models\AppSetting;
use App\Services\Cache\CacheVersionService;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('megtagadja a frissítést, ha nincs update jogosultság', function (): void {
    $user = $this->createAdminUser();
    $setting = AppSetting::query()->create([
        'key' => 'leave.cutoff_days',
        'value' => 5,
        'type' => 'int',
        'group' => 'leave',
    ]);

    $user->syncRoles([]);
    $user->syncPermissions([]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $this->actingAs($user)
        ->putJson(route('admin.app_settings.update', $setting->id), [
            'key' => 'leave.cutoff_days',
            'type' => 'int',
            'group' => 'leave',
            'value' => 6,
        ])
        ->assertForbidden();
});

it('unique kulcsot vár update-nél is', function (): void {
    $user = $this->createAdminUser();

    $first = AppSetting::query()->create([
        'key' => 'leave.first',
        'value' => 1,
        'type' => 'int',
        'group' => 'leave',
    ]);

    AppSetting::query()->create([
        'key' => 'leave.second',
        'value' => 2,
        'type' => 'int',
        'group' => 'leave',
    ]);

    $this->actingAs($user)
        ->putJson(route('admin.app_settings.update', $first->id), [
            'key' => 'leave.second',
            'type' => 'int',
            'group' => 'leave',
            'value' => 10,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['key']);
});

it('frissít és landlord cache verziót bumpol', function (): void {
    $user = $this->createAdminUser();
    $setting = AppSetting::query()->create([
        'key' => 'ui.compact_mode',
        'value' => false,
        'type' => 'bool',
        'group' => 'ui',
    ]);

    $versioner = app(CacheVersionService::class);
    $before = $versioner->get('landlord:app_settings.fetch');

    $this->actingAs($user)
        ->putJson(route('admin.app_settings.update', $setting->id), [
            'key' => 'ui.compact_mode',
            'type' => 'bool',
            'group' => 'ui',
            'label' => 'Compact mode',
            'value' => true,
        ])
        ->assertOk()
        ->assertJsonPath('data.value', true);

    $this->assertDatabaseHas('app_settings', [
        'id' => $setting->id,
        'key' => 'ui.compact_mode',
        'type' => 'bool',
        'group' => 'ui',
    ]);

    expect($versioner->get('landlord:app_settings.fetch'))->toBeGreaterThan($before);
});
