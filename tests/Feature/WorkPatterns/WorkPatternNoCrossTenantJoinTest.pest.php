<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeWorkPattern;
use App\Models\TenantGroup;
use App\Models\WorkPattern;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('does not leak assigned employees across companies', function (): void {
    $tenantGroup = TenantGroup::factory()->create();
    $companyOne = Company::factory()->create(['tenant_group_id' => $tenantGroup->id]);
    $companyTwo = Company::factory()->create(['tenant_group_id' => $tenantGroup->id]);

    $patternOne = WorkPattern::factory()->create(['company_id' => $companyOne->id]);
    $patternTwo = WorkPattern::factory()->create(['company_id' => $companyTwo->id]);

    $employeeOne = Employee::factory()->create(['company_id' => $companyOne->id]);
    $employeeTwo = Employee::factory()->create(['company_id' => $companyTwo->id]);

    EmployeeWorkPattern::factory()->create([
        'company_id' => $companyOne->id,
        'employee_id' => $employeeOne->id,
        'work_pattern_id' => $patternOne->id,
    ]);

    EmployeeWorkPattern::factory()->create([
        'company_id' => $companyTwo->id,
        'employee_id' => $employeeTwo->id,
        'work_pattern_id' => $patternTwo->id,
    ]);

    $user = $this->createAdminUser($companyOne);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $this->actingAs($user)
        ->getJson(route('work_patterns.employees', ['id' => $patternOne->id, 'company_id' => $companyOne->id]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.employee_id', $employeeOne->id);

    $this->actingAs($user)
        ->getJson(route('work_patterns.employees', ['id' => $patternTwo->id, 'company_id' => $companyOne->id]))
        ->assertNotFound();
});

it('repository does not use DB table queries', function (): void {
    $content = file_get_contents(app_path('Repositories/WorkPatternRepository.php'));

    expect($content)->not()->toContain('DB::table');
});

