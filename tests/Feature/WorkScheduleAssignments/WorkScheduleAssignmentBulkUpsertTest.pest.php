<?php

declare(strict_types=1);

use App\Models\Employee;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Models\WorkShift;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('allows bulk assignment for multiple employees on the same date range', function (): void {
    [, $company] = $this->createTenantWithCompany();
    $admin = $this->createAdminUser($company);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $admin->refresh();

    $schedule = WorkSchedule::factory()->create([
        'company_id' => $company->id,
        'status' => 'draft',
    ]);
    $shift = WorkShift::factory()->create(['company_id' => $company->id]);
    $employeeA = Employee::factory()->create(['company_id' => $company->id]);
    $employeeB = Employee::factory()->create(['company_id' => $company->id]);
    $employeeC = Employee::factory()->create(['company_id' => $company->id]);

    $dates = ['2026-04-10', '2026-04-11'];

    $this->actingAsUserInCompany($admin, $company)
        ->postJson(route('work_schedule_assignments.bulk_upsert'), [
            'work_schedule_id' => $schedule->id,
            'work_shift_id' => $shift->id,
            'employee_ids' => [$employeeA->id, $employeeB->id, $employeeC->id],
            'dates' => $dates,
        ])
        ->assertOk()
        ->assertJsonPath('count', 6);

    foreach ([$employeeA, $employeeB, $employeeC] as $employee) {
        foreach ($dates as $date) {
            $this->assertDatabaseHas('work_shift_assignments', [
                'company_id' => $company->id,
                'work_schedule_id' => $schedule->id,
                'work_shift_id' => $shift->id,
                'employee_id' => $employee->id,
                'date' => $date,
            ]);
        }
    }
});

it('rejects bulk assignment when employee_ids is empty or user lacks permission', function (): void {
    [, $company] = $this->createTenantWithCompany();
    $schedule = WorkSchedule::factory()->create([
        'company_id' => $company->id,
        'status' => 'draft',
    ]);
    $shift = WorkShift::factory()->create(['company_id' => $company->id]);

    $admin = $this->createAdminUser($company);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $admin->refresh();

    $this->actingAsUserInCompany($admin, $company)
        ->postJson(route('work_schedule_assignments.bulk_upsert'), [
            'work_schedule_id' => $schedule->id,
            'work_shift_id' => $shift->id,
            'employee_ids' => [],
            'dates' => ['2026-04-10'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['employee_ids']);

    /** @var User $user */
    $user = User::factory()->create();
    $user->assignRole('user');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $employee = Employee::factory()->create(['company_id' => $company->id]);

    $this->actingAsUserInCompany($user, $company)
        ->postJson(route('work_schedule_assignments.bulk_upsert'), [
            'work_schedule_id' => $schedule->id,
            'work_shift_id' => $shift->id,
            'employee_ids' => [$employee->id],
            'dates' => ['2026-04-10'],
        ])
        ->assertForbidden();
});

it('rejects bulk assignment for employee from another tenant or company scope', function (): void {
    [, $companyA] = $this->createTenantWithCompany();
    [, $companyB] = $this->createTenantWithCompany();

    $admin = $this->createAdminUser($companyA);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $admin->refresh();

    $schedule = WorkSchedule::factory()->create([
        'company_id' => $companyA->id,
        'status' => 'draft',
    ]);
    $shift = WorkShift::factory()->create(['company_id' => $companyA->id]);
    $foreignEmployee = Employee::factory()->create(['company_id' => $companyB->id]);

    $this->actingAsUserInCompany($admin, $companyA)
        ->postJson(route('work_schedule_assignments.bulk_upsert'), [
            'work_schedule_id' => $schedule->id,
            'work_shift_id' => $shift->id,
            'employee_ids' => [$foreignEmployee->id],
            'dates' => ['2026-04-10'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['employee_ids']);

    $this->assertDatabaseCount('work_shift_assignments', 0);
});
