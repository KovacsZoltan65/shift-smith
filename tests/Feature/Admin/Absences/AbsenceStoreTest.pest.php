<?php

declare(strict_types=1);

use App\Models\Employee;
use App\Models\LeaveType;
use App\Models\SickLeaveCategory;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('letrehoz tavolletet sajat company employee es leave type mellett', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($company);
    $employee = Employee::factory()->create(['company_id' => $company->id]);
    $leaveType = LeaveType::factory()->create([
        'company_id' => $company->id,
        'category' => 'leave',
    ]);

    $this->actingAsUserInCompany($user, $company)
        ->postJson(route('admin.absences.store'), [
            'employee_ids' => [$employee->id],
            'leave_type_id' => $leaveType->id,
            'date_from' => '2026-03-10',
            'date_to' => '2026-03-12',
            'note' => 'Tavaszi szabadsag',
        ])
        ->assertCreated()
        ->assertJsonPath('count', 1)
        ->assertJsonPath('data.0.employee_id', $employee->id)
        ->assertJsonPath('data.0.leave_type_id', $leaveType->id)
        ->assertJsonPath('data.0.total_minutes', 1440);
});

it('masik company employee-jere nem enged tavolletet rogzitni', function (): void {
    [$tenantA, $companyA] = $this->createTenantWithCompany();
    [$tenantB, $companyB] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($companyA);
    $employee = Employee::factory()->create(['company_id' => $companyB->id]);
    $leaveType = LeaveType::factory()->create([
        'company_id' => $companyA->id,
        'category' => 'leave',
    ]);

    $this->actingAsUserInCompany($user, $companyA)
        ->postJson(route('admin.absences.store'), [
            'employee_ids' => [$employee->id],
            'leave_type_id' => $leaveType->id,
            'date_from' => '2026-03-10',
            'date_to' => '2026-03-10',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['employee_ids.0']);
});

it('betegszabadsag eseten elmenti a sajat company sick leave category azonositojat', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($company);
    $employee = Employee::factory()->create(['company_id' => $company->id]);
    $leaveType = LeaveType::factory()->create([
        'company_id' => $company->id,
        'category' => LeaveType::CATEGORY_SICK_LEAVE,
    ]);
    $category = SickLeaveCategory::factory()->create([
        'company_id' => $company->id,
        'name' => 'Sajat betegseg',
        'code' => 'slc_own',
    ]);

    $this->actingAsUserInCompany($user, $company)
        ->postJson(route('admin.absences.store'), [
            'employee_ids' => [$employee->id],
            'leave_type_id' => $leaveType->id,
            'sick_leave_category_id' => $category->id,
            'date_from' => '2026-03-10',
            'date_to' => '2026-03-12',
            'note' => 'Betegseg miatt',
        ])
        ->assertCreated()
        ->assertJsonPath('data.0.sick_leave_category_id', $category->id)
        ->assertJsonPath('data.0.sick_leave_category_name', 'Sajat betegseg');
});

it('masik company sick leave kategoriat nem fogad el tavollet menteskor', function (): void {
    [$tenantA, $companyA] = $this->createTenantWithCompany();
    [$tenantB, $companyB] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($companyA);
    $employee = Employee::factory()->create(['company_id' => $companyA->id]);
    $leaveType = LeaveType::factory()->create([
        'company_id' => $companyA->id,
        'category' => LeaveType::CATEGORY_SICK_LEAVE,
    ]);
    $foreignCategory = SickLeaveCategory::factory()->create([
        'company_id' => $companyB->id,
        'name' => 'Masik ceg kategoria',
        'code' => 'slc_other_company',
    ]);

    $this->actingAsUserInCompany($user, $companyA)
        ->postJson(route('admin.absences.store'), [
            'employee_ids' => [$employee->id],
            'leave_type_id' => $leaveType->id,
            'sick_leave_category_id' => $foreignCategory->id,
            'date_from' => '2026-03-10',
            'date_to' => '2026-03-10',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['sick_leave_category_id']);
});
