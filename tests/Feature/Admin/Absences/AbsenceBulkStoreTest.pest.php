<?php

declare(strict_types=1);

use App\Models\Employee;
use App\Models\EmployeeAbsence;
use App\Models\LeaveType;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Models\WorkShift;
use App\Models\WorkShiftAssignment;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('creates absences for multiple employees in a single bulk request', function (): void {
    [, $company] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($company);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $employees = Employee::factory()->count(3)->create(['company_id' => $company->id]);
    $leaveType = LeaveType::factory()->create([
        'company_id' => $company->id,
        'category' => 'leave',
    ]);

    $response = $this->actingAsUserInCompany($user, $company)
        ->postJson(route('admin.absences.store'), [
            'employee_ids' => $employees->pluck('id')->all(),
            'leave_type_id' => $leaveType->id,
            'date_from' => '2026-04-14',
            'date_to' => '2026-04-15',
            'note' => 'Bulk távollét',
        ])
        ->assertCreated()
        ->assertJsonPath('count', 3);

    foreach ($employees as $employee) {
        $this->assertDatabaseHas('employee_absences', [
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'date_from' => '2026-04-14',
            'date_to' => '2026-04-15',
            'note' => 'Bulk távollét',
        ]);
    }

    expect($response->json('data'))->toHaveCount(3);
});

it('validates empty employee list with 422', function (): void {
    [, $company] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($company);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $leaveType = LeaveType::factory()->create([
        'company_id' => $company->id,
        'category' => 'leave',
    ]);

    $this->actingAsUserInCompany($user, $company)
        ->postJson(route('admin.absences.store'), [
            'employee_ids' => [],
            'leave_type_id' => $leaveType->id,
            'date_from' => '2026-04-14',
            'date_to' => '2026-04-15',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['employee_ids']);
});

it('requires create permission for bulk absence marking', function (): void {
    [, $company] = $this->createTenantWithCompany();
    /** @var User $user */
    $user = User::factory()->create();
    $user->assignRole('user');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $employee = Employee::factory()->create(['company_id' => $company->id]);
    $leaveType = LeaveType::factory()->create([
        'company_id' => $company->id,
        'category' => 'leave',
    ]);

    $this->actingAsUserInCompany($user, $company)
        ->postJson(route('admin.absences.store'), [
            'employee_ids' => [$employee->id],
            'leave_type_id' => $leaveType->id,
            'date_from' => '2026-04-14',
            'date_to' => '2026-04-15',
        ])
        ->assertForbidden();
});

it('rejects employee from another tenant during bulk absence marking', function (): void {
    [, $companyA] = $this->createTenantWithCompany();
    [, $companyB] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($companyA);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $foreignEmployee = Employee::factory()->create(['company_id' => $companyB->id]);
    $leaveType = LeaveType::factory()->create([
        'company_id' => $companyA->id,
        'category' => 'leave',
    ]);

    $this->actingAsUserInCompany($user, $companyA)
        ->postJson(route('admin.absences.store'), [
            'employee_ids' => [$foreignEmployee->id],
            'leave_type_id' => $leaveType->id,
            'date_from' => '2026-04-14',
            'date_to' => '2026-04-15',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['employee_ids.0']);
});

it('fails on first conflict when an employee already has absence or shift assignment in range', function (): void {
    [, $company] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($company);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $employee = Employee::factory()->create(['company_id' => $company->id]);
    $leaveType = LeaveType::factory()->create([
        'company_id' => $company->id,
        'category' => 'leave',
    ]);

    EmployeeAbsence::factory()->create([
        'company_id' => $company->id,
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'date_from' => '2026-04-15',
        'date_to' => '2026-04-16',
    ]);

    $this->actingAsUserInCompany($user, $company)
        ->postJson(route('admin.absences.store'), [
            'employee_ids' => [$employee->id],
            'leave_type_id' => $leaveType->id,
            'date_from' => '2026-04-14',
            'date_to' => '2026-04-15',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['employee_ids']);

    $employeeTwo = Employee::factory()->create(['company_id' => $company->id]);
    $schedule = WorkSchedule::factory()->create(['company_id' => $company->id, 'status' => 'draft']);
    $shift = WorkShift::factory()->create(['company_id' => $company->id]);
    WorkShiftAssignment::factory()->create([
        'company_id' => $company->id,
        'work_schedule_id' => $schedule->id,
        'employee_id' => $employeeTwo->id,
        'work_shift_id' => $shift->id,
        'date' => '2026-04-18',
    ]);

    $this->actingAsUserInCompany($user, $company)
        ->postJson(route('admin.absences.store'), [
            'employee_ids' => [$employeeTwo->id],
            'leave_type_id' => $leaveType->id,
            'date_from' => '2026-04-18',
            'date_to' => '2026-04-19',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['employee_ids']);
});
