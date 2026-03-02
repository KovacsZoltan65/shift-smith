<?php

declare(strict_types=1);

use App\Models\AppSetting;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('megtagadja a bulk delete-et, ha nincs deleteAny jogosultság', function (): void {
    $user = $this->createAdminUser();
    $ids = AppSetting::query()->insertGetId([
        'key' => 'leave.bulk_denied',
        'value' => json_encode(1),
        'type' => 'int',
        'group' => 'leave',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $user->syncRoles([]);
    $user->syncPermissions([]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $this->actingAs($user)
        ->deleteJson(route('admin.app_settings.destroy_bulk'), ['ids' => [$ids]])
        ->assertForbidden();
});

it('törli a kijelölt landlord app settingeket', function (): void {
    $user = $this->createAdminUser();
    $first = AppSetting::query()->create([
        'key' => 'leave.bulk_a',
        'value' => 1,
        'type' => 'int',
        'group' => 'leave',
    ]);
    $second = AppSetting::query()->create([
        'key' => 'leave.bulk_b',
        'value' => 2,
        'type' => 'int',
        'group' => 'leave',
    ]);

    $this->actingAs($user)
        ->deleteJson(route('admin.app_settings.destroy_bulk'), [
            'ids' => [$first->id, $second->id],
        ])
        ->assertOk()
        ->assertJsonPath('deleted', 2);

    $this->assertDatabaseMissing('app_settings', ['id' => $first->id]);
    $this->assertDatabaseMissing('app_settings', ['id' => $second->id]);
});
