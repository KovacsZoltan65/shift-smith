<?php

declare(strict_types=1);

use App\Models\AppSetting;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\SettingsMeta;
use App\Models\User;
use App\Models\UserSetting;
use App\Services\SettingsResolverService;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('default értéket ad vissza ha nincs override', function (): void {
    SettingsMeta::query()->create([
        'key' => 'test.settings.a',
        'group' => 'test',
        'label' => 'Test A',
        'type' => 'int',
        'default_value' => 10,
        'order_index' => 1,
        'is_editable' => true,
        'is_visible' => true,
    ]);

    $resolver = app(SettingsResolverService::class);
    $result = $resolver->effectiveValue('test.settings.a', []);

    expect($result['value'])->toBe(10)
        ->and($result['source'])->toBe('default');
});

it('helyes precedence-t alkalmaz: user > company > app > default', function (): void {
    $company = Company::factory()->create();
    $user = User::factory()->create();

    SettingsMeta::query()->create([
        'key' => 'test.settings.b',
        'group' => 'test',
        'label' => 'Test B',
        'type' => 'int',
        'default_value' => 1,
        'order_index' => 1,
        'is_editable' => true,
        'is_visible' => true,
    ]);

    AppSetting::query()->create(['key' => 'test.settings.b', 'value' => 2]);
    CompanySetting::query()->create(['company_id' => $company->id, 'key' => 'test.settings.b', 'value' => 3]);
    UserSetting::query()->create([
        'user_id' => $user->id,
        'company_id' => $company->id,
        'key' => 'test.settings.b',
        'value' => 4,
    ]);

    $resolver = app(SettingsResolverService::class);

    $appResult = $resolver->effectiveValue('test.settings.b', []);
    $companyResult = $resolver->effectiveValue('test.settings.b', ['company_id' => $company->id]);
    $userResult = $resolver->effectiveValue('test.settings.b', ['company_id' => $company->id, 'user_id' => $user->id]);

    expect($appResult['value'])->toBe(2)->and($appResult['source'])->toBe('app');
    expect($companyResult['value'])->toBe(3)->and($companyResult['source'])->toBe('company');
    expect($userResult['value'])->toBe(4)->and($userResult['source'])->toBe('user');
});

it('az app.locale kulcs global user override-ot használ company felett', function (): void {
    $company = Company::factory()->create();
    $user = User::factory()->create();

    SettingsMeta::query()->create([
        'key' => 'app.locale',
        'group' => 'localization',
        'label' => 'Alkalmazás nyelve',
        'type' => 'select',
        'default_value' => 'hu',
        'options' => [
            ['label' => 'English', 'value' => 'en'],
            ['label' => 'Magyar', 'value' => 'hu'],
        ],
        'validation' => ['required', 'in:en,hu'],
        'order_index' => 1,
        'is_editable' => true,
        'is_visible' => true,
    ]);

    AppSetting::query()->create(['key' => 'app.locale', 'value' => 'hu']);
    CompanySetting::query()->create(['company_id' => $company->id, 'key' => 'app.locale', 'value' => 'hu']);
    UserSetting::query()->create([
        'user_id' => $user->id,
        'company_id' => null,
        'key' => 'app.locale',
        'value' => 'en',
    ]);

    $resolver = app(SettingsResolverService::class);
    $result = $resolver->effectiveValue('app.locale', [
        'company_id' => $company->id,
        'user_id' => $user->id,
    ]);

    expect($result['value'])->toBe('en')
        ->and($result['source'])->toBe('user');
});
