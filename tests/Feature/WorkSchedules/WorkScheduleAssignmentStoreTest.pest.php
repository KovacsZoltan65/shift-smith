<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Employee;
use App\Models\WorkSchedule;
use App\Models\WorkScheduleAssignment;
use App\Models\WorkShift;
use App\Services\Cache\CacheVersionService;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('megtagadja a létrehozást jogosultság nélkül', function (): void {
    $user = $this->createAdminUser();
    $user->syncRoles([]);
    $user->syncPermissions([]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $company = Company::factory()->create();
    $schedule = WorkSchedule::factory()->create([
        'company_id' => $company->id,
        'date_from' => '2026-02-01',
        'date_to' => '2026-02-28',
    ]);
    $employee = Employee::factory()->create(['company_id' => $company->id]);
    $shift = WorkShift::factory()->create(['company_id' => $company->id]);

    $this->actingAs($user)
        ->postJson(route('work_schedule_assignments.store', ['schedule' => $schedule->id]), [
            'employee_id' => $employee->id,
            'work_shift_id' => $shift->id,
            'day' => '2026-02-10',
        ])
        ->assertForbidden();
});

it('validálja a day tartományt és tenant scope-ot', function (): void {
    $user = $this->createAdminUser();

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    $schedule = WorkSchedule::factory()->create([
        'company_id' => $companyA->id,
        'date_from' => '2026-02-01',
        'date_to' => '2026-02-28',
    ]);
    $foreignEmployee = Employee::factory()->create(['company_id' => $companyB->id]);
    $shift = WorkShift::factory()->create(['company_id' => $companyA->id]);

    $response = $this->actingAs($user)
        ->postJson(route('work_schedule_assignments.store', ['schedule' => $schedule->id]), [
            'employee_id' => $foreignEmployee->id,
            'work_shift_id' => $shift->id,
            'day' => '2026-03-01',
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['employee_id', 'day']);
});

it('létrehozza az assignmentet és bumpolja a cache verziót', function (): void {
    $user = $this->createAdminUser();

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $company = Company::factory()->create();
    $schedule = WorkSchedule::factory()->create([
        'company_id' => $company->id,
        'date_from' => '2026-02-01',
        'date_to' => '2026-02-28',
    ]);
    $employee = Employee::factory()->create(['company_id' => $company->id]);
    $shift = WorkShift::factory()->create(['company_id' => $company->id]);

    $versioner = app(CacheVersionService::class);
    Cache::forever("v:work_schedule_assignments.company_{$company->id}", 1);

    $this->actingAs($user)
        ->postJson(route('work_schedule_assignments.store', ['schedule' => $schedule->id]), [
            'employee_id' => $employee->id,
            'work_shift_id' => $shift->id,
            'day' => '2026-02-10',
            'start_time' => '08:00:00',
            'end_time' => '16:00:00',
        ])
        ->assertCreated();

    $this->assertDatabaseHas('work_schedule_assignments', [
        'company_id' => $company->id,
        'work_schedule_id' => $schedule->id,
        'employee_id' => $employee->id,
        'work_shift_id' => $shift->id,
        'day' => '2026-02-10',
        'deleted_at' => null,
    ]);

    expect($versioner->get("work_schedule_assignments.company_{$company->id}"))->toBe(2);
});

it('unique ütközésnél 422 hibát ad', function (): void {
    $user = $this->createAdminUser();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $company = Company::factory()->create();
    $schedule = WorkSchedule::factory()->create([
        'company_id' => $company->id,
        'date_from' => '2026-02-01',
        'date_to' => '2026-02-28',
    ]);
    $employee = Employee::factory()->create(['company_id' => $company->id]);
    $shift = WorkShift::factory()->create(['company_id' => $company->id]);

    WorkScheduleAssignment::factory()->create([
        'company_id' => $company->id,
        'work_schedule_id' => $schedule->id,
        'employee_id' => $employee->id,
        'work_shift_id' => $shift->id,
        'day' => '2026-02-12',
    ]);

    $this->actingAs($user)
        ->postJson(route('work_schedule_assignments.store', ['schedule' => $schedule->id]), [
            'employee_id' => $employee->id,
            'work_shift_id' => $shift->id,
            'day' => '2026-02-12',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['day']);
});
