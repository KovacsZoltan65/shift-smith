<?php

declare(strict_types=1);

use App\Facades\Settings;
use App\Models\AppSetting;
use App\Models\Company;
use App\Services\Cache\CacheVersionService;
use App\Services\Leave\LeaveSettings;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

function seedAgeBonusSettings(array $table): void
{
    AppSetting::query()->updateOrCreate(
        ['key' => 'leave.minutes_per_day'],
        [
            'value' => 480,
            'type' => 'int',
            'group' => 'leave',
            'label' => 'Munkaidő percekben egy napra',
            'description' => '8 óra = 480 perc',
        ]
    );

    AppSetting::query()->updateOrCreate(
        ['key' => 'leave.annual.base_minutes'],
        [
            'value' => 20 * 480,
            'type' => 'int',
            'group' => 'leave',
            'label' => 'Alapszabadság éves percekben',
            'description' => '20 napnyi alapszabadság percben',
        ]
    );

    AppSetting::query()->updateOrCreate(
        ['key' => 'leave.annual.age_bonus_table'],
        [
            'value' => $table,
            'type' => 'json',
            'group' => 'leave',
            'label' => 'Életkor szerinti pótszabadság táblázat',
            'description' => 'Az életkor alapján járó pótszabadság éves mértéke percben',
        ]
    );

    app(CacheVersionService::class)->bump('landlord:app_settings.show');
}

function defaultAgeBonusTable(): array
{
    return [
        ['age_from' => 25, 'extra_minutes_per_year' => 480],
        ['age_from' => 28, 'extra_minutes_per_year' => 960],
        ['age_from' => 31, 'extra_minutes_per_year' => 1440],
        ['age_from' => 33, 'extra_minutes_per_year' => 1920],
        ['age_from' => 35, 'extra_minutes_per_year' => 2400],
        ['age_from' => 37, 'extra_minutes_per_year' => 2880],
        ['age_from' => 39, 'extra_minutes_per_year' => 3360],
        ['age_from' => 41, 'extra_minutes_per_year' => 3840],
        ['age_from' => 43, 'extra_minutes_per_year' => 4320],
        ['age_from' => 45, 'extra_minutes_per_year' => 4800],
    ];
}

function setLeaveTenantContext(Company $company): void
{
    session()->put([
        'current_company_id' => (int) $company->id,
        'current_tenant_group_id' => (int) $company->tenant_group_id,
    ]);
}

it('returns 0 minutes for age 24', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($company);

    seedAgeBonusSettings(defaultAgeBonusTable());

    $this->actingAsUserInCompany($user, $company);
    setLeaveTenantContext($company);

    expect(LeaveSettings::ageBonusMinutes(24))->toBe(0);
});

it('returns 480 minutes for age 25', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($company);

    seedAgeBonusSettings(defaultAgeBonusTable());

    $this->actingAsUserInCompany($user, $company);
    setLeaveTenantContext($company);

    expect(LeaveSettings::ageBonusMinutes(25))->toBe(480);
});

it('returns 960 minutes for age 30', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($company);

    seedAgeBonusSettings(defaultAgeBonusTable());

    $this->actingAsUserInCompany($user, $company);
    setLeaveTenantContext($company);

    expect(LeaveSettings::ageBonusMinutes(30))->toBe(960);
});

it('returns 4800 minutes for age 45', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($company);

    seedAgeBonusSettings(defaultAgeBonusTable());

    $this->actingAsUserInCompany($user, $company);
    setLeaveTenantContext($company);

    expect(LeaveSettings::ageBonusMinutes(45))->toBe(4800);
});

it('returns max cap for age 50', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($company);

    seedAgeBonusSettings(defaultAgeBonusTable());

    $this->actingAsUserInCompany($user, $company);
    setLeaveTenantContext($company);

    expect(LeaveSettings::ageBonusMinutes(50))->toBe(4800);
});

it('does not break tenant isolation', function (): void {
    [$tenantA, $companyA] = $this->createTenantWithCompany();
    [$tenantB, $companyB] = $this->createTenantWithCompany();
    $userA = $this->createAdminUser($companyA);

    seedAgeBonusSettings(defaultAgeBonusTable());

    $this->actingAsUserInCompany($userA, $companyA);
    setLeaveTenantContext($companyA);
    $tenantA->makeCurrent();

    expect(LeaveSettings::minutesPerDay())->toBe(480)
        ->and(LeaveSettings::baseMinutes())->toBe(9600)
        ->and(LeaveSettings::ageBonusMinutes(45))->toBe(4800);

    session()->put([
        'current_company_id' => (int) $companyB->id,
        'current_tenant_group_id' => (int) $tenantA->id,
    ]);

    expect(Settings::get('leave.annual.age_bonus_table'))->toBeArray()
        ->and(LeaveSettings::ageBonusMinutes(30))->toBe(960);
});

it('returns 0 when the age bonus table json is empty', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($company);

    seedAgeBonusSettings([]);

    $this->actingAsUserInCompany($user, $company);
    setLeaveTenantContext($company);

    expect(LeaveSettings::ageBonusMinutes(45))->toBe(0);
});
