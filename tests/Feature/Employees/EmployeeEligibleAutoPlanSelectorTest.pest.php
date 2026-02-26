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
    $this->get(route('employees.selector', ['eligible_for_autoplan' => 1]))->assertRedirect();
});

it('megtagadja az eligible autoplan selector lekérést jogosultság nélkül', function (): void {
    $user = $this->createAdminUser();
    $user->syncRoles([]);
    $user->syncPermissions([]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $company = $user->companies()->firstOrFail();

    $this->actingAs($user)
        ->withSession([
            'current_company_id' => (int) $company->id,
            'current_tenant_group_id' => (int) $company->tenant_group_id,
        ])
        ->getJson(route('employees.selector', ['eligible_for_autoplan' => 1]))
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

    $outsideRange = Employee::factory()->create([
        'company_id' => $companyA->id,
        'active' => true,
    ]);
    EmployeeWorkPattern::factory()->create([
        'company_id' => $companyA->id,
        'employee_id' => $outsideRange->id,
        'work_pattern_id' => $pattern480A->id,
        'date_from' => '2026-01-01',
        'date_to' => '2026-01-31',
    ]);

    $boundaryOverlap = Employee::factory()->create([
        'company_id' => $companyA->id,
        'active' => true,
    ]);
    EmployeeWorkPattern::factory()->create([
        'company_id' => $companyA->id,
        'employee_id' => $boundaryOverlap->id,
        'work_pattern_id' => $pattern480A->id,
        'date_from' => '2026-03-31',
        'date_to' => null,
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
        ->getJson(route('employees.selector', [
            'eligible_for_autoplan' => 1,
            'required_daily_minutes' => 480,
            'date_from' => '2026-03-01',
            'date_to' => '2026-03-31',
        ]));

    $response->assertOk();
    $response->assertJsonPath('meta.eligible_count', 2);
    $response->assertJsonPath('meta.excluded_count', 4);
    $response->assertJsonPath('meta.excluded_reasons.inactive', 1);
    $response->assertJsonPath('meta.excluded_reasons.not_matching_minutes', 1);
    $response->assertJsonPath('meta.excluded_reasons.missing_pattern', 2);

    $ids = collect($response->json('data'))->pluck('id')->map(fn ($x): int => (int) $x)->all();

    expect($ids)->toContain($eligible->id);
    expect($ids)->toContain($boundaryOverlap->id);
    expect($ids)->not->toContain($inactive->id);
    expect($ids)->not->toContain($not480->id);
    expect($ids)->not->toContain($outsideRange->id);
    expect($ids)->not->toContain($foreignEligible->id);
});
