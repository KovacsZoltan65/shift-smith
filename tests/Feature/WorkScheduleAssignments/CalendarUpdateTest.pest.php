<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Employee;
use App\Models\WorkSchedule;
use App\Models\WorkShift;
use App\Models\WorkShiftAssignment;
use Carbon\CarbonImmutable;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('nem enged múltbeli szerkesztést', function (): void {
    $user = $this->createAdminUser();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $today = CarbonImmutable::today();
    $pastDate = $today->subDay()->toDateString();

    $company = Company::factory()->create();
    $schedule = WorkSchedule::factory()->create([
        'company_id' => $company->id,
        'date_from' => $today->subDays(7)->toDateString(),
        'date_to' => $today->addDays(7)->toDateString(),
        'status' => 'draft',
    ]);
    $employee = Employee::factory()->create(['company_id' => $company->id]);
    $shiftA = WorkShift::factory()->create(['company_id' => $company->id]);
    $shiftB = WorkShift::factory()->create(['company_id' => $company->id]);

    $assignment = WorkShiftAssignment::factory()->create([
        'company_id' => $company->id,
        'work_schedule_id' => $schedule->id,
        'employee_id' => $employee->id,
        'work_shift_id' => $shiftA->id,
        'date' => $pastDate,
    ]);

    $this->actingAs($user)
        ->withSession(['current_company_id' => $company->id])
        ->putJson(route('work_schedule_assignments.update', ['id' => $assignment->id]), [
            'work_schedule_id' => $schedule->id,
            'employee_id' => $employee->id,
            'work_shift_id' => $shiftB->id,
            'date' => $pastDate,
        ])
        ->assertForbidden();
});

it('engedi az aktuális vagy jövőbeni módosítást', function (): void {
    $user = $this->createAdminUser();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $today = CarbonImmutable::today();
    $futureDate = $today->addDay()->toDateString();

    $company = Company::factory()->create();
    $schedule = WorkSchedule::factory()->create([
        'company_id' => $company->id,
        'date_from' => $today->toDateString(),
        'date_to' => $today->addDays(10)->toDateString(),
        'status' => 'draft',
    ]);
    $employee = Employee::factory()->create(['company_id' => $company->id]);
    $shiftA = WorkShift::factory()->create(['company_id' => $company->id]);
    $shiftB = WorkShift::factory()->create(['company_id' => $company->id]);

    $assignment = WorkShiftAssignment::factory()->create([
        'company_id' => $company->id,
        'work_schedule_id' => $schedule->id,
        'employee_id' => $employee->id,
        'work_shift_id' => $shiftA->id,
        'date' => $today->toDateString(),
    ]);

    $this->actingAs($user)
        ->withSession(['current_company_id' => $company->id])
        ->putJson(route('work_schedule_assignments.update', ['id' => $assignment->id]), [
            'work_schedule_id' => $schedule->id,
            'employee_id' => $employee->id,
            'work_shift_id' => $shiftB->id,
            'date' => $futureDate,
        ])
        ->assertOk();
});
