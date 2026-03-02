<?php

declare(strict_types=1);

use App\Facades\Settings;
use App\Models\AppSetting;
use App\Models\CompanySetting;
use App\Models\SettingsMeta;
use App\Models\User;
use App\Models\UserSetting;
use App\Models\Company;
use App\Services\Cache\CacheVersionService;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

function seedSettingsMeta(string $key, string $type = 'string', mixed $defaultValue = null): void
{
    SettingsMeta::query()->updateOrCreate(
        ['key' => $key],
        [
            'group' => 'test',
            'label' => $key,
            'type' => $type,
            'default_value' => $defaultValue,
            'description' => 'test meta',
            'options' => null,
            'validation' => null,
            'order_index' => 1,
            'is_editable' => true,
            'is_visible' => true,
        ]
    );
}

function setCurrentCompanyContext(Company $company): void
{
    session()->put([
        'current_company_id' => (int) $company->id,
        'current_tenant_group_id' => (int) $company->tenant_group_id,
    ]);
}

it('user company scoped felulirja a company-t', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $actor = $this->createAdminUser($company);
    $target = User::factory()->create();

    seedSettingsMeta('settings.priority');

    CompanySetting::query()->create([
        'company_id' => $company->id,
        'key' => 'settings.priority',
        'value' => 'company',
        'type' => 'string',
        'group' => 'test',
    ]);

    UserSetting::query()->create([
        'user_id' => $actor->id,
        'company_id' => $company->id,
        'key' => 'settings.priority',
        'value' => 'user',
    ]);

    $this->actingAsUserInCompany($actor, $company);
    setCurrentCompanyContext($company);

    expect(Settings::get('settings.priority'))->toBe('user')
        ->and(Settings::getEffective('settings.priority')->source)->toBe('user');
});

it('company felulirja az appot', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $actor = $this->createAdminUser($company);

    seedSettingsMeta('settings.threshold', 'int', 1);

    AppSetting::query()->create([
        'key' => 'settings.threshold',
        'value' => 2,
        'type' => 'int',
        'group' => 'test',
    ]);

    CompanySetting::query()->create([
        'company_id' => $company->id,
        'key' => 'settings.threshold',
        'value' => 7,
        'type' => 'int',
        'group' => 'test',
    ]);

    $this->actingAsUserInCompany($actor, $company);
    setCurrentCompanyContext($company);

    expect(Settings::get('settings.threshold'))->toBe(7)
        ->and(Settings::getEffective('settings.threshold')->source)->toBe('company');
});

it('app ertek marad ha nincs feluliras', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $actor = $this->createAdminUser($company);

    seedSettingsMeta('settings.from_app', 'string', 'meta-default');

    AppSetting::query()->create([
        'key' => 'settings.from_app',
        'value' => 'app-value',
        'type' => 'string',
        'group' => 'test',
    ]);

    $this->actingAsUserInCompany($actor, $company);
    setCurrentCompanyContext($company);

    expect(Settings::get('settings.from_app'))->toBe('app-value')
        ->and(Settings::getEffective('settings.from_app')->source)->toBe('app');
});

it('default parameter mukodik ismeretlen kulcsnal', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $actor = $this->createAdminUser($company);

    $this->actingAsUserInCompany($actor, $company);
    setCurrentCompanyContext($company);

    expect(Settings::get('settings.unknown', 'fallback'))->toBe('fallback')
        ->and(Settings::getEffective('settings.unknown', 'fallback')->source)->toBe('default');
});

it('getInt es getBool castol', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $actor = $this->createAdminUser($company);

    seedSettingsMeta('settings.limit', 'int', 0);
    seedSettingsMeta('settings.enabled', 'bool', false);

    AppSetting::query()->create([
        'key' => 'settings.limit',
        'value' => '12',
        'type' => 'int',
        'group' => 'test',
    ]);

    AppSetting::query()->create([
        'key' => 'settings.enabled',
        'value' => '1',
        'type' => 'bool',
        'group' => 'test',
    ]);

    $this->actingAsUserInCompany($actor, $company);
    setCurrentCompanyContext($company);

    expect(Settings::getInt('settings.limit'))->toBe(12)
        ->and(Settings::getBool('settings.enabled'))->toBeTrue();
});

it('tenant izolaciot tart ket tenant group es ket company kozott', function (): void {
    [$tenantA, $companyA] = $this->createTenantWithCompany();
    [$tenantB, $companyB] = $this->createTenantWithCompany();
    $actorA = $this->createAdminUser($companyA);
    $actorB = $this->createAdminUser($companyB);

    seedSettingsMeta('settings.isolated', 'string', 'meta-default');

    CompanySetting::query()->create([
        'company_id' => $companyA->id,
        'key' => 'settings.isolated',
        'value' => 'tenant-a',
        'type' => 'string',
        'group' => 'test',
    ]);

    CompanySetting::query()->create([
        'company_id' => $companyB->id,
        'key' => 'settings.isolated',
        'value' => 'tenant-b',
        'type' => 'string',
        'group' => 'test',
    ]);

    $this->actingAsUserInCompany($actorA, $companyA);
    setCurrentCompanyContext($companyA);
    expect(Settings::get('settings.isolated'))->toBe('tenant-a');

    $this->actingAsUserInCompany($actorB, $companyB);
    setCurrentCompanyContext($companyB);
    expect(Settings::get('settings.isolated'))->toBe('tenant-b');
});

it('legacy fallback csak flag true eseten aktiv', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $actor = $this->createAdminUser($company);

    seedSettingsMeta('settings.legacy', 'string', 'meta-default');

    AppSetting::query()->create([
        'key' => 'settings.user_legacy_global_override_enabled',
        'value' => false,
        'type' => 'bool',
        'group' => 'settings',
    ]);

    CompanySetting::query()->create([
        'company_id' => $company->id,
        'key' => 'settings.legacy',
        'value' => 'company',
        'type' => 'string',
        'group' => 'test',
    ]);

    UserSetting::query()->create([
        'user_id' => $actor->id,
        'company_id' => null,
        'key' => 'settings.legacy',
        'value' => 'legacy-user',
    ]);

    $this->actingAsUserInCompany($actor, $company);
    setCurrentCompanyContext($company);

    expect(Settings::get('settings.legacy'))->toBe('company')
        ->and(Settings::getEffective('settings.legacy')->source)->toBe('company');

    $flag = AppSetting::query()->where('key', 'settings.user_legacy_global_override_enabled')->firstOrFail();
    $flag->value = true;
    $flag->save();

    app(CacheVersionService::class)->bump('landlord:app_settings.show');

    Settings::flushCache($company->id, $actor->id);

    expect(Settings::get('settings.legacy'))->toBe('legacy-user')
        ->and(Settings::getEffective('settings.legacy')->source)->toBe('user_legacy');
});
