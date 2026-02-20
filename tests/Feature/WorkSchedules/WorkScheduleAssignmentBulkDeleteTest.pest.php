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

it('megtagadja a bulk törlést jogosultság nélkül', function (): void {
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

    $itemA = WorkScheduleAssignment::factory()->create([
        'company_id' => $company->id,
        'work_schedule_id' => $schedule->id,
        'employee_id' => $employee->id,
        'work_shift_id' => $shift->id,
        'day' => '2026-02-10',
    ]);
    $itemB = WorkScheduleAssignment::factory()->create([
        'company_id' => $company->id,
        'work_schedule_id' => $schedule->id,
        'employee_id' => $employee->id,
        'work_shift_id' => $shift->id,
        'day' => '2026-02-11',
    ]);

    $this->actingAs($user)
        ->deleteJson(route('work_schedule_assignments.destroy_bulk', ['schedule' => $schedule->id]), [
            'ids' => [(int) $itemA->id, (int) $itemB->id],
        ])
        ->assertForbidden();
});

it('bulk soft delete + cache bump működik', function (): void {
    $user = $this->createAdminUser();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $company = Company::factory()->create();
    $schedule = WorkSchedule::factory()->create([
        'company_id' => $company->id,
        'date_from' => '2026-02-01',
        'date_to' => '2026-02-28',
    ]);
    $shift = WorkShift::factory()->create(['company_id' => $company->id]);

    $employees = Employee::factory()->count(3)->create(['company_id' => $company->id]);
    $items = collect($employees)->values()->map(function (Employee $employee, int $index) use ($company, $schedule, $shift) {
        return WorkScheduleAssignment::factory()->create([
            'company_id' => $company->id,
            'work_schedule_id' => $schedule->id,
            'employee_id' => $employee->id,
            'work_shift_id' => $shift->id,
            'day' => sprintf('2026-02-%02d', 10 + $index),
        ]);
    });

    $ids = $items->pluck('id')->all();

    $versioner = app(CacheVersionService::class);
    Cache::forever("v:work_schedule_assignments.company_{$company->id}", 1);

    $this->actingAs($user)
        ->deleteJson(route('work_schedule_assignments.destroy_bulk', ['schedule' => $schedule->id]), [
            'ids' => $ids,
        ])
        ->assertOk()
        ->assertJson(['deleted' => 3]);

    foreach ($ids as $id) {
        $this->assertSoftDeleted('work_schedule_assignments', ['id' => $id]);
    }

    expect($versioner->get("work_schedule_assignments.company_{$company->id}"))->toBe(2);
});
