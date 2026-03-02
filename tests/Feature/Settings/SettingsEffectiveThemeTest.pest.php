<?php

declare(strict_types=1);

use App\Facades\Settings;
use App\Models\AppSetting;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\SettingsMeta;
use App\Models\User;
use App\Models\UserSetting;
use App\Services\Cache\CacheVersionService;
use App\Services\UserSettingService;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

function seedThemeSettingMeta(): void
{
    SettingsMeta::query()->updateOrCreate(
        ['key' => 'themes.color_theme'],
        [
            'group' => 'themes',
            'label' => 'Color theme',
            'type' => 'string',
            'default_value' => 'default-theme',
            'description' => 'Theme selector',
            'options' => ['dark', 'light', 'system'],
            'validation' => ['nullable', 'string'],
            'order_index' => 1,
            'is_editable' => true,
            'is_visible' => true,
        ]
    );
}

function seedLegacyFlag(bool $enabled): void
{
    AppSetting::query()->updateOrCreate(
        ['key' => 'settings.user_legacy_global_override_enabled'],
        [
            'value' => $enabled,
            'type' => 'bool',
            'group' => 'settings',
            'label' => 'Legacy user fallback',
            'description' => 'Legacy user fallback flag',
        ]
    );
}

function seedThemeStack(Company $company, User $user, bool $withUser = true): void
{
    AppSetting::query()->updateOrCreate(
        ['key' => 'themes.color_theme'],
        [
            'value' => 'dark',
            'type' => 'string',
            'group' => 'themes',
            'label' => 'Color theme',
            'description' => 'Theme selector',
        ]
    );

    CompanySetting::query()->updateOrCreate(
        ['company_id' => $company->id, 'key' => 'themes.color_theme'],
        [
            'value' => 'light',
            'type' => 'string',
            'group' => 'themes',
            'label' => 'Color theme',
            'description' => 'Theme selector',
        ]
    );

    if (! $withUser) {
        return;
    }

    UserSetting::query()->updateOrCreate(
        ['user_id' => $user->id, 'company_id' => $company->id, 'key' => 'themes.color_theme'],
        [
            'value' => 'system',
            'type' => 'string',
            'group' => 'themes',
            'label' => 'Color theme',
            'description' => 'Theme selector',
        ]
    );
}

function setCurrentThemeContext(Company $company): void
{
    session()->put([
        'current_company_id' => (int) $company->id,
        'current_tenant_group_id' => (int) $company->tenant_group_id,
    ]);
}

it('user wins for themes.color_theme in current company scope', function (): void {
    [$tenantA, $companyA] = $this->createTenantWithCompany();
    [$tenantB, $companyB] = $this->createTenantWithCompany();
    $userA = $this->createAdminUser($companyA);
    $userB = $this->createAdminUser($companyB);

    seedThemeSettingMeta();
    seedLegacyFlag(false);
    seedThemeStack($companyA, $userA, withUser: true);
    seedThemeStack($companyB, $userB, withUser: true);

    $this->actingAsUserInCompany($userA, $companyA);
    setCurrentThemeContext($companyA);

    expect(Settings::get('themes.color_theme'))->toBe('system')
        ->and(Settings::getEffective('themes.color_theme')->source)->toBe('user');
});

it('falls back to company value when user scoped setting is missing', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($company);

    seedThemeSettingMeta();
    seedLegacyFlag(false);
    seedThemeStack($company, $user, withUser: false);

    $this->actingAsUserInCompany($user, $company);
    setCurrentThemeContext($company);

    expect(Settings::get('themes.color_theme'))->toBe('light')
        ->and(Settings::getEffective('themes.color_theme')->source)->toBe('company');
});

it('falls back to app value when company and user scoped settings are missing', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($company);

    seedThemeSettingMeta();
    seedLegacyFlag(false);

    AppSetting::query()->updateOrCreate(
        ['key' => 'themes.color_theme'],
        [
            'value' => 'dark',
            'type' => 'string',
            'group' => 'themes',
            'label' => 'Color theme',
            'description' => 'Theme selector',
        ]
    );

    $this->actingAsUserInCompany($user, $company);
    setCurrentThemeContext($company);

    expect(Settings::get('themes.color_theme'))->toBe('dark')
        ->and(Settings::getEffective('themes.color_theme')->source)->toBe('app');
});

it('uses legacy user fallback only when the feature flag is enabled', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($company);

    seedThemeSettingMeta();
    seedThemeStack($company, $user, withUser: false);
    seedLegacyFlag(false);

    UserSetting::query()->updateOrCreate(
        ['user_id' => $user->id, 'company_id' => null, 'key' => 'themes.color_theme'],
        [
            'value' => 'system',
            'type' => 'string',
            'group' => 'themes',
            'label' => 'Color theme',
            'description' => 'Theme selector',
        ]
    );

    $this->actingAsUserInCompany($user, $company);
    setCurrentThemeContext($company);

    expect(Settings::get('themes.color_theme'))->toBe('light')
        ->and(Settings::getEffective('themes.color_theme')->source)->toBe('company');

    $flag = AppSetting::query()->where('key', 'settings.user_legacy_global_override_enabled')->firstOrFail();
    $flag->value = true;
    $flag->save();

    app(CacheVersionService::class)->bump('landlord:app_settings.show');
    Settings::flushCache($company->id, $user->id);

    expect(Settings::get('themes.color_theme'))->toBe('system')
        ->and(Settings::getEffective('themes.color_theme')->source)->toBe('user_legacy');
});

it('does not leak another tenant company setting through a drifted session context', function (): void {
    [$tenantA, $companyA] = $this->createTenantWithCompany();
    [$tenantB, $companyB] = $this->createTenantWithCompany();
    $userA = $this->createAdminUser($companyA);

    seedThemeSettingMeta();
    seedLegacyFlag(false);

    AppSetting::query()->updateOrCreate(
        ['key' => 'themes.color_theme'],
        [
            'value' => 'dark',
            'type' => 'string',
            'group' => 'themes',
            'label' => 'Color theme',
            'description' => 'Theme selector',
        ]
    );

    CompanySetting::query()->updateOrCreate(
        ['company_id' => $companyB->id, 'key' => 'themes.color_theme'],
        [
            'value' => 'tenant-b-only',
            'type' => 'string',
            'group' => 'themes',
            'label' => 'Color theme',
            'description' => 'Theme selector',
        ]
    );

    $this->actingAs($userA)->withSession([
        'current_company_id' => (int) $companyB->id,
        'current_tenant_group_id' => (int) $tenantA->id,
    ]);

    session()->put([
        'current_company_id' => (int) $companyB->id,
        'current_tenant_group_id' => (int) $tenantA->id,
    ]);
    $tenantA->makeCurrent();

    expect(Settings::get('themes.color_theme'))->toBe('dark')
        ->and(Settings::getEffective('themes.color_theme')->source)->toBe('app')
        ->and(session()->get('current_company_id'))->toBeNull();
});

it('invalidates cached effective value after user setting mutation', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($company);

    seedThemeSettingMeta();
    seedLegacyFlag(false);
    seedThemeStack($company, $user, withUser: true);

    $this->actingAsUserInCompany($user, $company);
    setCurrentThemeContext($company);

    $beforeVersion = app(CacheVersionService::class)->get("effective_settings:{$company->id}:all");

    expect(Settings::get('themes.color_theme'))->toBe('system');

    /** @var UserSetting $setting */
    $setting = UserSetting::query()
        ->where('user_id', $user->id)
        ->where('company_id', $company->id)
        ->where('key', 'themes.color_theme')
        ->firstOrFail();

    app(UserSettingService::class)->update($company->id, $user->id, (int) $setting->id, [
        'key' => 'themes.color_theme',
        'value' => 'light',
        'type' => 'string',
        'group' => 'themes',
        'label' => 'Color theme',
        'description' => 'Theme selector',
    ]);

    expect(app(CacheVersionService::class)->get("effective_settings:{$company->id}:all"))->toBeGreaterThan($beforeVersion)
        ->and(Settings::get('themes.color_theme'))->toBe('light')
        ->and(Settings::getEffective('themes.color_theme')->source)->toBe('user');
});
