<?php

declare(strict_types=1);

use App\Models\AppSetting;
use App\Models\Employee;
use App\Services\Cache\CacheVersionService;
use App\Services\Leave\LeaveEntitlementCalculator;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

function seedAnnualEntitlementSettings(): void
{
    $minutesPerDay = 480;

    $rows = [
        [
            'key' => 'leave.minutes_per_day',
            'value' => $minutesPerDay,
            'type' => 'int',
            'group' => 'leave',
            'label' => 'Munkaidő percekben egy napra',
            'description' => '8 óra = 480 perc.',
        ],
        [
            'key' => 'leave.annual.base_minutes',
            'value' => 20 * $minutesPerDay,
            'type' => 'int',
            'group' => 'leave',
            'label' => 'Alapszabadság éves percekben',
            'description' => '20 nap alapszabadság percben.',
        ],
        [
            'key' => 'leave.annual.age_bonus_table',
            'value' => [
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
            ],
            'type' => 'json',
            'group' => 'leave',
            'label' => 'Életkor szerinti pótszabadság táblázat',
            'description' => 'Életkor alapú pótszabadság percben.',
        ],
        [
            'key' => 'leave.annual.child_bonus_table',
            'value' => [
                'by_children_count' => [
                    '1' => 2 * $minutesPerDay,
                    '2' => 4 * $minutesPerDay,
                    '3' => 7 * $minutesPerDay,
                ],
                'disabled_child_extra_per_child_minutes' => 2 * $minutesPerDay,
            ],
            'type' => 'json',
            'group' => 'leave',
            'label' => 'Gyermekek utáni pótszabadság',
            'description' => 'Gyermekszám és fogyatékos gyermek alapján járó pótszabadság.',
        ],
        [
            'key' => 'leave.youth.extra_minutes',
            'value' => 5 * $minutesPerDay,
            'type' => 'int',
            'group' => 'leave',
            'label' => 'Fiatal munkavállalói pótszabadság',
            'description' => '18 év alattiak éves pótszabadsága.',
        ],
        [
            'key' => 'leave.disability.extra_minutes',
            'value' => 5 * $minutesPerDay,
            'type' => 'int',
            'group' => 'leave',
            'label' => 'Fogyatékosság miatti pótszabadság',
            'description' => 'Fogyatékosság esetén járó éves pótszabadság.',
        ],
    ];

    foreach ($rows as $row) {
        AppSetting::query()->updateOrCreate(
            ['key' => $row['key']],
            [
                'value' => $row['value'],
                'type' => $row['type'],
                'group' => $row['group'],
                'label' => $row['label'],
                'description' => $row['description'],
            ],
        );
    }

    app(CacheVersionService::class)->bump('landlord:app_settings.show');
}

function leaveEmployee(array $attributes = []): Employee
{
    $profileAttributes = [
        'children_count' => 0,
        'disabled_children_count' => 0,
        'is_disabled' => false,
    ];

    foreach (['children_count', 'disabled_children_count', 'is_disabled'] as $key) {
        if (array_key_exists($key, $attributes)) {
            $profileAttributes[$key] = $attributes[$key];
            unset($attributes[$key]);
        }
    }

    $employee = Employee::factory()->create($attributes);

    \App\Models\EmployeeProfile::factory()->create([
        'company_id' => (int) $employee->company_id,
        'employee_id' => (int) $employee->id,
        ...$profileAttributes,
    ]);

    return $employee;
}

it('calculates base only entitlement', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($company);
    seedAnnualEntitlementSettings();

    $employee = leaveEmployee([
        'company_id' => $company->id,
        'birth_date' => '2000-02-10',
    ]);

    $this->actingAsUserInCompany($user, $company);

    $result = app(LeaveEntitlementCalculator::class)->calculateAnnualMinutesForEmployee((int) $employee->id, 2025);

    expect($result->base_minutes)->toBe(9600)
        ->and($result->age_bonus_minutes)->toBe(0)
        ->and($result->child_bonus_minutes)->toBe(0)
        ->and($result->youth_bonus_minutes)->toBe(0)
        ->and($result->disability_bonus_minutes)->toBe(0)
        ->and($result->total_minutes)->toBe(9600);
});

it('adds age bonus for a 45 year old employee', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($company);
    seedAnnualEntitlementSettings();

    $employee = leaveEmployee([
        'company_id' => $company->id,
        'birth_date' => '1980-01-01',
    ]);

    $this->actingAsUserInCompany($user, $company);

    $result = app(LeaveEntitlementCalculator::class)->calculateAnnualMinutesForEmployee((int) $employee->id, 2025);

    expect($result->age_bonus_minutes)->toBe(4800)
        ->and($result->total_minutes)->toBe(14400);
});

it('adds child bonus based on children count and disabled child extra', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($company);
    seedAnnualEntitlementSettings();

    $twoChildren = leaveEmployee([
        'company_id' => $company->id,
        'children_count' => 2,
    ]);
    $threeChildren = leaveEmployee([
        'company_id' => $company->id,
        'email' => fake()->unique()->safeEmail(),
        'children_count' => 3,
        'disabled_children_count' => 1,
    ]);

    $this->actingAsUserInCompany($user, $company);
    $calculator = app(LeaveEntitlementCalculator::class);

    $twoChildrenResult = $calculator->calculateAnnualMinutesForEmployee((int) $twoChildren->id, 2025);
    $threeChildrenResult = $calculator->calculateAnnualMinutesForEmployee((int) $threeChildren->id, 2025);

    expect($twoChildrenResult->child_bonus_minutes)->toBe(1920)
        ->and($twoChildrenResult->total_minutes)->toBe(11520)
        ->and($threeChildrenResult->child_bonus_minutes)->toBe(4320)
        ->and($threeChildrenResult->total_minutes)->toBe(13920);
});

it('adds youth bonus for an employee under 18 at the start of the year', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($company);
    seedAnnualEntitlementSettings();

    $employee = leaveEmployee([
        'company_id' => $company->id,
        'birth_date' => '2008-06-15',
    ]);

    $this->actingAsUserInCompany($user, $company);

    $result = app(LeaveEntitlementCalculator::class)->calculateAnnualMinutesForEmployee((int) $employee->id, 2025);

    expect($result->youth_bonus_minutes)->toBe(2400)
        ->and($result->total_minutes)->toBe(12000);
});

it('adds disability bonus for disabled employees', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($company);
    seedAnnualEntitlementSettings();

    $employee = leaveEmployee([
        'company_id' => $company->id,
        'is_disabled' => true,
    ]);

    $this->actingAsUserInCompany($user, $company);

    $result = app(LeaveEntitlementCalculator::class)->calculateAnnualMinutesForEmployee((int) $employee->id, 2025);

    expect($result->disability_bonus_minutes)->toBe(2400)
        ->and($result->total_minutes)->toBe(12000);
});

it('adds all entitlement components together', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($company);
    seedAnnualEntitlementSettings();

    $employee = leaveEmployee([
        'company_id' => $company->id,
        'birth_date' => '2008-01-01',
        'children_count' => 3,
        'disabled_children_count' => 1,
        'is_disabled' => true,
    ]);

    $this->actingAsUserInCompany($user, $company);

    $result = app(LeaveEntitlementCalculator::class)->calculateAnnualMinutesForEmployee((int) $employee->id, 2025);

    expect($result->base_minutes)->toBe(9600)
        ->and($result->age_bonus_minutes)->toBe(0)
        ->and($result->child_bonus_minutes)->toBe(4320)
        ->and($result->youth_bonus_minutes)->toBe(2400)
        ->and($result->disability_bonus_minutes)->toBe(2400)
        ->and($result->total_minutes)->toBe(18720)
        ->and($result->breakdown)->toMatchArray([
            'base_minutes' => 9600,
            'child_bonus_minutes' => 4320,
            'youth_bonus_minutes' => 2400,
            'disability_bonus_minutes' => 2400,
        ]);
});

it('rejects employees outside the current company scope and tenant', function (): void {
    [$tenantA, $companyA] = $this->createTenantWithCompany();
    [, $companyWithinTenantA] = $this->createTenantWithCompany([], ['tenant_group_id' => $tenantA->id]);
    [$tenantB, $companyB] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($companyA);
    seedAnnualEntitlementSettings();

    $sameTenantOtherCompany = leaveEmployee([
        'company_id' => $companyWithinTenantA->id,
    ]);

    $this->actingAsUserInCompany($user, $companyA);

    expect(fn () => app(LeaveEntitlementCalculator::class)->calculateAnnualMinutesForEmployee((int) $sameTenantOtherCompany->id, 2025))
        ->toThrow(DomainException::class);

    $foreignEmployee = leaveEmployee([
        'company_id' => $companyB->id,
        'email' => fake()->unique()->safeEmail(),
    ]);

    $tenantA->makeCurrent();

    expect(fn () => app(LeaveEntitlementCalculator::class)->calculateAnnualMinutesForEmployee((int) $foreignEmployee->id, 2025))
        ->toThrow(DomainException::class);
});
