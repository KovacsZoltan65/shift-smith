<?php

declare(strict_types=1);

use App\Models\AppSetting;
use App\Models\Company;
use App\Models\Employee;
use App\Models\TenantGroup;
use App\Services\Cache\CacheVersionService;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

function seedEntitlementSettings(): void
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
            'label' => 'Életkor szerinti pótszabadság',
            'description' => 'Életkor alapú pótszabadság percben.',
        ],
        [
            'key' => 'leave.annual.child_bonus_table',
            'value' => [
                'by_children_count' => [
                    '1' => 960,
                    '2' => 1920,
                    '3' => 3360,
                ],
                'disabled_child_extra_per_child_minutes' => 960,
            ],
            'type' => 'json',
            'group' => 'leave',
            'label' => 'Gyermek utáni pótszabadság',
            'description' => 'Gyermek után járó pótszabadság percben.',
        ],
        [
            'key' => 'leave.youth.extra_minutes',
            'value' => 2400,
            'type' => 'int',
            'group' => 'leave',
            'label' => 'Fiatal munkavállalói pótszabadság',
            'description' => '18 év alattiak pótszabadsága percben.',
        ],
        [
            'key' => 'leave.disability.extra_minutes',
            'value' => 2400,
            'type' => 'int',
            'group' => 'leave',
            'label' => 'Fogyatékosság miatti pótszabadság',
            'description' => 'Fogyatékosság esetén járó pótszabadság.',
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

it('returns 403 without employees.view permission', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $employee = Employee::factory()->create(['company_id' => $company->id]);
    $user = $this->createAdminUser($company);

    $user->syncRoles([]);
    $user->syncPermissions([]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();
    $tenant->makeCurrent();

    $this->actingAs($user)->withSession([
        'current_company_id' => (int) $company->id,
        'current_tenant_group_id' => (int) $tenant->id,
    ])->getJson(route('employees.leave_entitlement', ['id' => $employee->id]))
        ->assertStatus(302);
});

it('returns 403 for employee from another company', function (): void {
    $tenant = TenantGroup::factory()->create();
    $companyA = Company::factory()->create(['tenant_group_id' => $tenant->id]);
    $companyB = Company::factory()->create(['tenant_group_id' => $tenant->id]);
    $employee = Employee::factory()->create(['company_id' => $companyB->id]);
    $user = $this->createAdminUser($companyA);
    $user->companies()->syncWithoutDetaching([$companyB->id]);

    $tenant->makeCurrent();

    $this->actingAs($user)->withSession([
        'current_company_id' => (int) $companyA->id,
        'current_tenant_group_id' => (int) $tenant->id,
    ])->getJson(route('employees.leave_entitlement', ['id' => $employee->id]))
        ->assertNotFound();
});

it('returns annual leave entitlement dto for the selected company employee', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($company);
    seedEntitlementSettings();

    $employee = Employee::factory()->create([
        'company_id' => $company->id,
        'birth_date' => '1980-01-01',
        'children_count' => 2,
        'disabled_children_count' => 1,
        'is_disabled' => true,
    ]);

    $tenant->makeCurrent();

    $this->actingAs($user)->withSession([
        'current_company_id' => (int) $company->id,
        'current_tenant_group_id' => (int) $tenant->id,
    ])->getJson(route('employees.leave_entitlement', [
        'id' => $employee->id,
        'year' => 2025,
    ]))
        ->assertOk()
        ->assertJsonPath('data.employee_id', (int) $employee->id)
        ->assertJsonPath('data.company_id', (int) $company->id)
        ->assertJsonPath('data.year', 2025)
        ->assertJsonPath('data.base_minutes', 9600)
        ->assertJsonPath('data.age_bonus_minutes', 4800)
        ->assertJsonPath('data.child_bonus_minutes', 2880)
        ->assertJsonPath('data.disability_bonus_minutes', 2400)
        ->assertJsonPath('data.youth_bonus_minutes', 0)
        ->assertJsonPath('data.total_minutes', 19680);
});
