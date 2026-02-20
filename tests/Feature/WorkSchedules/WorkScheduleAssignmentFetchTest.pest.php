<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Employee;
use App\Models\WorkSchedule;
use App\Models\WorkScheduleAssignment;
use App\Models\WorkShift;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('átirányítja a vendéget a schedule assignment fetch végpontról', function (): void {
    $company = Company::factory()->create();
    $schedule = WorkSchedule::factory()->create(['company_id' => $company->id]);

    $this->get(route('work_schedule_assignments.fetch', ['schedule' => $schedule->id]))
        ->assertRedirect();
});

it('megtagadja a fetch-et jogosultság nélkül', function (): void {
    $user = $this->createAdminUser();
    $user->syncRoles([]);
    $user->syncPermissions([]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $schedule = WorkSchedule::factory()->create();

    $this->actingAs($user)
        ->getJson(route('work_schedule_assignments.fetch', ['schedule' => $schedule->id]))
        ->assertForbidden();
});

it('csak a schedule-hoz tartozó assignmenteket adja vissza', function (): void {
    $user = $this->createAdminUser();

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $company = Company::factory()->create();
    $schedule = WorkSchedule::factory()->create([
        'company_id' => $company->id,
        'date_from' => '2026-02-01',
        'date_to' => '2026-02-28',
    ]);
    $otherSchedule = WorkSchedule::factory()->create([
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
        'day' => '2026-02-10',
    ]);

    WorkScheduleAssignment::factory()->create([
        'company_id' => $company->id,
        'work_schedule_id' => $otherSchedule->id,
        'employee_id' => $employee->id,
        'work_shift_id' => $shift->id,
        'day' => '2026-02-11',
    ]);

    $response = $this->actingAs($user)
        ->getJson(route('work_schedule_assignments.fetch', ['schedule' => $schedule->id]));

    $response->assertOk()
        ->assertJsonStructure([
            'message',
            'data',
            'meta' => ['current_page', 'per_page', 'total', 'last_page'],
            'filter',
        ]);

    expect($response->json('meta.total'))->toBe(1);
    expect($response->json('data.0.day'))->toBe('2026-02-10');
});
