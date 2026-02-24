<?php

declare(strict_types=1);

use App\Models\AppSetting;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\SettingsMeta;
use App\Models\User;
use App\Models\UserSetting;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('ment app szinten', function (): void {
    $user = $this->createSuperadminUser();

    SettingsMeta::query()->create([
        'key' => 'test.settings.save_app',
        'group' => 'test',
        'label' => 'Save app',
        'type' => 'int',
        'default_value' => 5,
        'validation' => ['required', 'integer', 'min:1'],
        'order_index' => 1,
        'is_editable' => true,
        'is_visible' => true,
    ]);

    $this->actingAs($user)
        ->withSession(['current_company_id' => Company::factory()->create()->id])
        ->postJson(route('settings.save'), [
            'level' => 'app',
            'values' => [
                ['key' => 'test.settings.save_app', 'value' => 9],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('data.saved_count', 1);

    $this->assertDatabaseHas('app_settings', [
        'key' => 'test.settings.save_app',
    ]);
});

it('törli a company override-ot, ha megegyezik a parent effective értékkel', function (): void {
    $user = $this->createSuperadminUser();
    $company = Company::factory()->create();

    SettingsMeta::query()->create([
        'key' => 'test.settings.company_delete',
        'group' => 'test',
        'label' => 'Company delete',
        'type' => 'int',
        'default_value' => 1,
        'validation' => ['required', 'integer'],
        'order_index' => 1,
        'is_editable' => true,
        'is_visible' => true,
    ]);

    AppSetting::query()->create(['key' => 'test.settings.company_delete', 'value' => 11]);
    CompanySetting::query()->create([
        'company_id' => $company->id,
        'key' => 'test.settings.company_delete',
        'value' => 22,
    ]);

    $this->actingAs($user)
        ->withSession(['current_company_id' => $company->id])
        ->postJson(route('settings.save'), [
            'level' => 'company',
            'company_id' => $company->id,
            'values' => [
                ['key' => 'test.settings.company_delete', 'value' => 11], // parent: app
            ],
        ])
        ->assertOk();

    $this->assertSoftDeleted('company_settings', [
        'company_id' => $company->id,
        'key' => 'test.settings.company_delete',
    ]);
});

it('tenant izoláció mellett csak a cél company szintet menti', function (): void {
    $user = $this->createSuperadminUser();
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    SettingsMeta::query()->create([
        'key' => 'test.settings.company_isolation',
        'group' => 'test',
        'label' => 'Company isolation',
        'type' => 'int',
        'default_value' => 10,
        'validation' => ['required', 'integer'],
        'order_index' => 1,
        'is_editable' => true,
        'is_visible' => true,
    ]);

    CompanySetting::query()->create([
        'company_id' => $companyB->id,
        'key' => 'test.settings.company_isolation',
        'value' => 77,
    ]);

    $this->actingAs($user)
        ->withSession(['current_company_id' => $companyA->id])
        ->postJson(route('settings.save'), [
            'level' => 'company',
            'company_id' => $companyA->id,
            'values' => [
                ['key' => 'test.settings.company_isolation', 'value' => 33],
            ],
        ])
        ->assertOk();

    $this->assertDatabaseHas('company_settings', [
        'company_id' => $companyA->id,
        'key' => 'test.settings.company_isolation',
    ]);

    $this->assertDatabaseHas('company_settings', [
        'company_id' => $companyB->id,
        'key' => 'test.settings.company_isolation',
    ]);
});

it('ment user szinten', function (): void {
    $actor = $this->createSuperadminUser();
    $targetUser = User::factory()->create();
    $company = Company::factory()->create();

    SettingsMeta::query()->create([
        'key' => 'test.settings.user_save',
        'group' => 'test',
        'label' => 'User save',
        'type' => 'bool',
        'default_value' => false,
        'validation' => ['required', 'boolean'],
        'order_index' => 1,
        'is_editable' => true,
        'is_visible' => true,
    ]);

    $this->actingAs($actor)
        ->withSession(['current_company_id' => $company->id])
        ->postJson(route('settings.save'), [
            'level' => 'user',
            'company_id' => $company->id,
            'user_id' => $targetUser->id,
            'values' => [
                ['key' => 'test.settings.user_save', 'value' => true],
            ],
        ])
        ->assertOk();

    $this->assertDatabaseHas('user_settings', [
        'user_id' => $targetUser->id,
        'key' => 'test.settings.user_save',
    ]);
});

