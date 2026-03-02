<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\CompanyEmployee;
use App\Models\Employee;
use App\Models\EmployeeWorkPattern;
use App\Models\WorkPattern;
use App\Models\WorkSchedule;
use App\Models\WorkShift;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

function attachEmployeeToWorkShiftCompany(Employee $employee, Company $company): void
{
    CompanyEmployee::query()->updateOrCreate(
        [
            'company_id' => (int) $company->id,
            'employee_id' => (int) $employee->id,
        ],
        [
            'active' => true,
        ]
    );
}

it('requires work pattern id when storing a work shift assignment', function (): void {
    $company = Company::factory()->create();
    $user = $this->createAdminUser($company);
    $shift = WorkShift::factory()->create(['company_id' => $company->id]);
    $employee = Employee::factory()->create(['company_id' => $company->id]);

    attachEmployeeToWorkShiftCompany($employee, $company);

    $this->actingAs($user)
        ->withSession([
            'current_company_id' => $company->id,
            'current_tenant_group_id' => $company->tenant_group_id,
        ])
        ->postJson(route('work_shift_assignments.store', ['work_shift' => $shift->id]), [
            'employee_id' => $employee->id,
            'date' => '2026-03-15',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['work_pattern_id']);
});

it('stores the work shift assignment and syncs the employee work pattern', function (): void {
    $company = Company::factory()->create();
    $user = $this->createAdminUser($company);
    $shift = WorkShift::factory()->create(['company_id' => $company->id]);
    $employee = Employee::factory()->create(['company_id' => $company->id]);
    $pattern = WorkPattern::factory()->create(['company_id' => $company->id, 'active' => true]);

    attachEmployeeToWorkShiftCompany($employee, $company);

    $this->actingAs($user)
        ->withSession([
            'current_company_id' => $company->id,
            'current_tenant_group_id' => $company->tenant_group_id,
        ])
        ->postJson(route('work_shift_assignments.store', ['work_shift' => $shift->id]), [
            'employee_id' => $employee->id,
            'work_pattern_id' => $pattern->id,
            'date' => '2026-03-15',
        ])
        ->assertCreated()
        ->assertJsonPath('data.employee_id', $employee->id)
        ->assertJsonPath('data.work_pattern_name', $pattern->name);

    $this->assertDatabaseHas('work_shift_assignments', [
        'company_id' => $company->id,
        'employee_id' => $employee->id,
        'work_shift_id' => $shift->id,
        'date' => '2026-03-15',
    ]);

    $this->assertDatabaseHas('employee_work_patterns', [
        'company_id' => $company->id,
        'employee_id' => $employee->id,
        'work_pattern_id' => $pattern->id,
        'date_from' => '2026-03-15',
        'date_to' => null,
    ]);

    expect(
        WorkSchedule::query()
            ->where('company_id', $company->id)
            ->where('name', sprintf('AUTO-WP-%d', $pattern->id))
            ->exists()
    )->toBeTrue();
});

it('rejects assigning a work pattern from another company', function (): void {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $user = $this->createAdminUser($companyA);
    $shift = WorkShift::factory()->create(['company_id' => $companyA->id]);
    $employee = Employee::factory()->create(['company_id' => $companyA->id]);
    $pattern = WorkPattern::factory()->create(['company_id' => $companyB->id]);

    attachEmployeeToWorkShiftCompany($employee, $companyA);

    $this->actingAs($user)
        ->withSession([
            'current_company_id' => $companyA->id,
            'current_tenant_group_id' => $companyA->tenant_group_id,
        ])
        ->postJson(route('work_shift_assignments.store', ['work_shift' => $shift->id]), [
            'employee_id' => $employee->id,
            'work_pattern_id' => $pattern->id,
            'date' => '2026-03-15',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['work_pattern_id']);

    expect(EmployeeWorkPattern::query()->count())->toBe(0);
});
