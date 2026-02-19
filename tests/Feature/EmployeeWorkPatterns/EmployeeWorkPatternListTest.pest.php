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

it('átirányítja a vendéget a dolgozó munkarend lista végpontról', function (): void {
    $company = Company::factory()->create();
    $employee = Employee::factory()->create(['company_id' => $company->id]);

    $this->get(route('employee_work_patterns.index', ['employee' => $employee->id]))->assertRedirect();
});

it('megtagadja a listázást jogosultság nélkül', function (): void {
    $user = $this->createAdminUser();
    $user->syncRoles([]);
    $user->syncPermissions([]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $employee = Employee::factory()->create();

    $this->actingAs($user)
        ->getJson(route('employee_work_patterns.index', ['employee' => $employee->id]))
        ->assertForbidden();
});

it('csak a kiválasztott dolgozó hozzárendeléseit adja vissza', function (): void {
    $user = $this->createAdminUser();

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $company = Company::factory()->create();
    $employee = Employee::factory()->create(['company_id' => $company->id]);
    $other = Employee::factory()->create(['company_id' => $company->id]);

    $pattern = WorkPattern::factory()->create(['company_id' => $company->id, 'name' => 'Minta 1']);
    $pattern2 = WorkPattern::factory()->create(['company_id' => $company->id, 'name' => 'Minta 2']);

    EmployeeWorkPattern::factory()->create([
        'company_id' => $company->id,
        'employee_id' => $employee->id,
        'work_pattern_id' => $pattern->id,
    ]);

    EmployeeWorkPattern::factory()->create([
        'company_id' => $company->id,
        'employee_id' => $other->id,
        'work_pattern_id' => $pattern2->id,
    ]);

    $resp = $this->actingAs($user)->getJson(route('employee_work_patterns.index', ['employee' => $employee->id]));

    $resp->assertOk()->assertJsonStructure(['message', 'data']);
    expect($resp->json('data'))->toHaveCount(1);
    expect($resp->json('data.0.employee_id'))->toBe($employee->id);
});
