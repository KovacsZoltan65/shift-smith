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

it('nem enged cégidegen hozzárendelés módosítást', function (): void {
    $user = $this->createAdminUser();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    $employeeA = Employee::factory()->create(['company_id' => $companyA->id]);
    $employeeB = Employee::factory()->create(['company_id' => $companyB->id]);
    $patternA = WorkPattern::factory()->create(['company_id' => $companyA->id]);

    $assignment = EmployeeWorkPattern::factory()->create([
        'company_id' => $companyA->id,
        'employee_id' => $employeeA->id,
        'work_pattern_id' => $patternA->id,
        'date_from' => '2026-01-01',
        'date_to' => null,
    ]);

    $this->actingAs($user)
        ->putJson(route('employee_work_patterns.update', [
            'employee' => $employeeB->id,
            'id' => $assignment->id,
        ]), [
            'work_pattern_id' => $patternA->id,
            'date_from' => '2026-02-01',
            'date_to' => null,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['employee']);
});

it('nem enged cégidegen hozzárendelés törlést', function (): void {
    $user = $this->createAdminUser();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    $employeeA = Employee::factory()->create(['company_id' => $companyA->id]);
    $employeeB = Employee::factory()->create(['company_id' => $companyB->id]);
    $patternA = WorkPattern::factory()->create(['company_id' => $companyA->id]);

    $assignment = EmployeeWorkPattern::factory()->create([
        'company_id' => $companyA->id,
        'employee_id' => $employeeA->id,
        'work_pattern_id' => $patternA->id,
    ]);

    $this->actingAs($user)
        ->deleteJson(route('employee_work_patterns.destroy', [
            'employee' => $employeeB->id,
            'id' => $assignment->id,
        ]))
        ->assertNotFound();
});
