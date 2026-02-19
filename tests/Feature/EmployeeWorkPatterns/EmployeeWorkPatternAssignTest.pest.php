<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeWorkPattern;
use App\Models\User;
use App\Models\WorkPattern;
use App\Services\Cache\CacheVersionService;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('megtagadja a hozzárendelést jogosultság nélkül', function (): void {
    $user = User::factory()->create();
    $user->assignRole('user');

    $company = Company::factory()->create();
    $employee = Employee::factory()->create(['company_id' => $company->id]);
    $pattern = WorkPattern::factory()->create(['company_id' => $company->id]);

    $this->actingAs($user)
        ->postJson(route('employee_work_patterns.assign', ['employee' => $employee->id]), [
            'work_pattern_id' => $pattern->id,
            'date_from' => '2026-01-01',
        ])
        ->assertForbidden();
});

it('validálja a dátum logikát és az átfedést', function (): void {
    $user = $this->createAdminUser();
    $company = Company::factory()->create();
    $employee = Employee::factory()->create(['company_id' => $company->id]);
    $pattern = WorkPattern::factory()->create(['company_id' => $company->id]);

    EmployeeWorkPattern::factory()->create([
        'company_id' => $company->id,
        'employee_id' => $employee->id,
        'work_pattern_id' => $pattern->id,
        'date_from' => '2026-01-01',
        'date_to' => '2026-12-31',
        'is_primary' => true,
    ]);

    $this->actingAs($user)
        ->postJson(route('employee_work_patterns.assign', ['employee' => $employee->id]), [
            'work_pattern_id' => $pattern->id,
            'date_from' => '2026-05-01',
            'date_to' => '2026-04-01',
            'is_primary' => true,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['date_to']);

    $this->actingAs($user)
        ->postJson(route('employee_work_patterns.assign', ['employee' => $employee->id]), [
            'work_pattern_id' => $pattern->id,
            'date_from' => '2026-06-01',
            'date_to' => '2026-10-01',
            'is_primary' => true,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['date_from']);
});

it('megakadályozza a más company-hoz tartozó munkarend hozzárendelését', function (): void {
    $user = $this->createAdminUser();

    $c1 = Company::factory()->create();
    $c2 = Company::factory()->create();

    $employee = Employee::factory()->create(['company_id' => $c1->id]);
    $pattern = WorkPattern::factory()->create(['company_id' => $c2->id]);

    $this->actingAs($user)
        ->postJson(route('employee_work_patterns.assign', ['employee' => $employee->id]), [
            'work_pattern_id' => $pattern->id,
            'date_from' => '2026-01-01',
            'is_primary' => true,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['work_pattern_id']);
});

it('létrehozza a hozzárendelést és bumpolja a cache verziót', function (): void {
    $user = $this->createAdminUser();

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $company = Company::factory()->create();
    $employee = Employee::factory()->create(['company_id' => $company->id]);
    $pattern = WorkPattern::factory()->create(['company_id' => $company->id]);

    $versioner = app(CacheVersionService::class);
    Cache::forever("v:employee_work_patterns.list.company_{$company->id}", 1);

    $this->actingAs($user)
        ->postJson(route('employee_work_patterns.assign', ['employee' => $employee->id]), [
            'work_pattern_id' => $pattern->id,
            'date_from' => '2026-01-01',
            'date_to' => null,
            'is_primary' => true,
        ])
        ->assertCreated()
        ->assertJsonPath('data.employee_id', $employee->id);

    $this->assertDatabaseHas('employee_work_patterns', [
        'employee_id' => $employee->id,
        'work_pattern_id' => $pattern->id,
    ]);

    expect($versioner->get("employee_work_patterns.list.company_{$company->id}"))->toBe(2);
});
