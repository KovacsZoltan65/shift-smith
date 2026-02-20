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

it('megtagadja a törlést jogosultság nélkül', function (): void {
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
    $assignment = WorkScheduleAssignment::factory()->create([
        'company_id' => $company->id,
        'work_schedule_id' => $schedule->id,
        'employee_id' => $employee->id,
        'work_shift_id' => $shift->id,
        'day' => '2026-02-10',
    ]);

    $this->actingAs($user)
        ->deleteJson(route('work_schedule_assignments.destroy', [
            'schedule' => $schedule->id,
            'id' => $assignment->id,
        ]))
        ->assertForbidden();
});

it('soft delete-t végez és bumpolja a cache verziót', function (): void {
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
    $assignment = WorkScheduleAssignment::factory()->create([
        'company_id' => $company->id,
        'work_schedule_id' => $schedule->id,
        'employee_id' => $employee->id,
        'work_shift_id' => $shift->id,
        'day' => '2026-02-10',
    ]);

    $versioner = app(CacheVersionService::class);
    Cache::forever("v:work_schedule_assignments.company_{$company->id}", 1);

    $this->actingAs($user)
        ->deleteJson(route('work_schedule_assignments.destroy', [
            'schedule' => $schedule->id,
            'id' => $assignment->id,
        ]))
        ->assertOk();

    $this->assertSoftDeleted('work_schedule_assignments', ['id' => $assignment->id]);
    expect($versioner->get("work_schedule_assignments.company_{$company->id}"))->toBe(2);
});
