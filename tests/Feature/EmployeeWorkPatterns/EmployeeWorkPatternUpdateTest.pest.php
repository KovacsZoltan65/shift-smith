<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\CompanyEmployee;
use App\Models\Employee;
use App\Models\EmployeeWorkPattern;
use App\Models\WorkPattern;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('update esetén tiltja az átfedő időszakot', function (): void {
    $company = Company::factory()->create();
    $user = $this->createAdminUser($company);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $employee = Employee::factory()->create(['company_id' => $company->id]);
    $patternA = WorkPattern::factory()->create(['company_id' => $company->id]);
    $patternB = WorkPattern::factory()->create(['company_id' => $company->id]);
    CompanyEmployee::query()->updateOrCreate(
        ['company_id' => $company->id, 'employee_id' => $employee->id],
        ['active' => true]
    );

    $first = EmployeeWorkPattern::factory()->create([
        'company_id' => $company->id,
        'employee_id' => $employee->id,
        'work_pattern_id' => $patternA->id,
        'date_from' => '2026-01-01',
        'date_to' => '2026-01-31',
    ]);

    EmployeeWorkPattern::factory()->create([
        'company_id' => $company->id,
        'employee_id' => $employee->id,
        'work_pattern_id' => $patternB->id,
        'date_from' => '2026-03-01',
        'date_to' => '2026-03-31',
    ]);

    $this->actingAs($user)
        ->withSession([
            'current_company_id' => (int) $company->id,
            'current_tenant_group_id' => (int) $company->tenant_group_id,
        ])
        ->putJson(route('employee_work_patterns.update', [
            'employee' => $employee->id,
            'id' => $first->id,
        ]), [
            'work_pattern_id' => $patternA->id,
            'date_from' => '2026-03-15',
            'date_to' => '2026-04-15',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['date_from']);
});
