<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\CompanyEmployee;
use App\Models\Employee;
use App\Models\EmployeeWorkPattern;
use App\Models\WorkPattern;
use App\Models\WorkShift;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

function attachEmployeeToPatternCompany(Employee $employee, Company $company): void
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

it('closes the previous active pattern and opens the new one on work shift assignment store', function (): void {
    $company = Company::factory()->create();
    $user = $this->createAdminUser($company);
    $shift = WorkShift::factory()->create(['company_id' => $company->id]);
    $employee = Employee::factory()->create(['company_id' => $company->id]);
    $oldPattern = WorkPattern::factory()->create(['company_id' => $company->id, 'name' => 'Old pattern']);
    $newPattern = WorkPattern::factory()->create(['company_id' => $company->id, 'name' => 'New pattern']);

    attachEmployeeToPatternCompany($employee, $company);

    EmployeeWorkPattern::factory()->create([
        'company_id' => $company->id,
        'employee_id' => $employee->id,
        'work_pattern_id' => $oldPattern->id,
        'date_from' => '2026-01-01',
        'date_to' => null,
    ]);

    $this->actingAs($user)
        ->withSession([
            'current_company_id' => $company->id,
            'current_tenant_group_id' => $company->tenant_group_id,
        ])
        ->postJson(route('work_shift_assignments.store', ['work_shift' => $shift->id]), [
            'employee_id' => $employee->id,
            'work_pattern_id' => $newPattern->id,
            'date' => '2026-03-15',
        ])
        ->assertCreated();

    $this->assertDatabaseHas('employee_work_patterns', [
        'company_id' => $company->id,
        'employee_id' => $employee->id,
        'work_pattern_id' => $oldPattern->id,
        'date_from' => '2026-01-01',
        'date_to' => '2026-03-14',
    ]);

    $this->assertDatabaseHas('employee_work_patterns', [
        'company_id' => $company->id,
        'employee_id' => $employee->id,
        'work_pattern_id' => $newPattern->id,
        'date_from' => '2026-03-15',
        'date_to' => null,
    ]);
});
