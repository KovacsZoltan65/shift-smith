<?php

declare(strict_types=1);

use App\Models\AppSetting;
use App\Services\Cache\CacheVersionService;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('megtagadja a törlést, ha nincs delete jogosultság', function (): void {
    $user = $this->createAdminUser();
    $setting = AppSetting::query()->create([
        'key' => 'leave.delete_me',
        'value' => 7,
        'type' => 'int',
        'group' => 'leave',
    ]);

    $user->syncRoles([]);
    $user->syncPermissions([]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $this->actingAs($user)
        ->deleteJson(route('admin.app_settings.destroy', $setting->id))
        ->assertForbidden();
});

it('töröl és landlord cache verziót bumpol', function (): void {
    $user = $this->createAdminUser();
    $setting = AppSetting::query()->create([
        'key' => 'leave.to_delete',
        'value' => 7,
        'type' => 'int',
        'group' => 'leave',
    ]);

    $versioner = app(CacheVersionService::class);
    $before = $versioner->get('landlord:app_settings.fetch');

    $this->actingAs($user)
        ->deleteJson(route('admin.app_settings.destroy', $setting->id))
        ->assertOk()
        ->assertJsonPath('deleted', true);

    $this->assertDatabaseMissing('app_settings', [
        'id' => $setting->id,
    ]);

    expect($versioner->get('landlord:app_settings.fetch'))->toBeGreaterThan($before);
});
