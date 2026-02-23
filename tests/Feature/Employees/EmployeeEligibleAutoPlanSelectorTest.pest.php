<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeWorkPattern;
use App\Models\WorkPattern;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('átirányítja a vendéget az eligible autoplan selector végpontról', function (): void {
    $this->get(route('selectors.employees.eligible_autoplan'))->assertRedirect();
});

it('megtagadja az eligible autoplan selector lekérést jogosultság nélkül', function (): void {
    $user = $this->createAdminUser();
    $user->syncRoles([]);
    $user->syncPermissions([]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $company = Company::factory()->create();

    $this->actingAs($user)
        ->withSession(['current_company_id' => $company->id])
        ->getJson(route('selectors.employees.eligible_autoplan', ['eligible_for_autoplan' => 1]))
        ->assertForbidden();
});

it('csak tenanton belüli aktív és 8 órás munkarenddel rendelkező dolgozókat ad vissza', function (): void {
    $user = $this->createSuperadminUser();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();
    $user->givePermissionTo('work_schedules.autoplan');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    $pattern480A = WorkPattern::factory()->create([
        'company_id' => $companyA->id,
        'daily_work_minutes' => 480,
        'active' => true,
    ]);
    $pattern420A = WorkPattern::factory()->create([
        'company_id' => $companyA->id,
        'daily_work_minutes' => 420,
        'active' => true,
    ]);
    $pattern480B = WorkPattern::factory()->create([
        'company_id' => $companyB->id,
        'daily_work_minutes' => 480,
        'active' => true,
    ]);

    $eligible = Employee::factory()->create([
        'company_id' => $companyA->id,
        'active' => true,
    ]);
    EmployeeWorkPattern::factory()->create([
        'company_id' => $companyA->id,
        'employee_id' => $eligible->id,
        'work_pattern_id' => $pattern480A->id,
        'date_from' => '2026-03-01',
        'date_to' => null,
    ]);

    $inactive = Employee::factory()->create([
        'company_id' => $companyA->id,
        'active' => false,
    ]);
    EmployeeWorkPattern::factory()->create([
        'company_id' => $companyA->id,
        'employee_id' => $inactive->id,
        'work_pattern_id' => $pattern480A->id,
        'date_from' => '2026-03-01',
        'date_to' => null,
    ]);

    $not480 = Employee::factory()->create([
        'company_id' => $companyA->id,
        'active' => true,
    ]);
    EmployeeWorkPattern::factory()->create([
        'company_id' => $companyA->id,
        'employee_id' => $not480->id,
        'work_pattern_id' => $pattern420A->id,
        'date_from' => '2026-03-01',
        'date_to' => null,
    ]);

    Employee::factory()->create([
        'company_id' => $companyA->id,
        'active' => true,
    ]);

    $foreignEligible = Employee::factory()->create([
        'company_id' => $companyB->id,
        'active' => true,
    ]);
    EmployeeWorkPattern::factory()->create([
        'company_id' => $companyB->id,
        'employee_id' => $foreignEligible->id,
        'work_pattern_id' => $pattern480B->id,
        'date_from' => '2026-03-01',
        'date_to' => null,
    ]);

    $response = $this->actingAs($user)
        ->withSession(['current_company_id' => $companyA->id])
        ->getJson(route('selectors.employees.eligible_autoplan', [
            'eligible_for_autoplan' => 1,
            'target_daily_minutes' => 480,
            'month' => '2026-03',
        ]));

    $response->assertOk();
    $response->assertJsonPath('meta.eligible_count', 1);
    $response->assertJsonPath('meta.excluded_count', 3);
    $response->assertJsonPath('meta.breakdown.inactive', 1);
    $response->assertJsonPath('meta.breakdown.not_target_daily_minutes', 2);

    $ids = collect($response->json('data'))->pluck('id')->map(fn ($x): int => (int) $x)->all();

    expect($ids)->toContain($eligible->id);
    expect($ids)->not->toContain($inactive->id);
    expect($ids)->not->toContain($not480->id);
    expect($ids)->not->toContain($foreignEligible->id);
});
