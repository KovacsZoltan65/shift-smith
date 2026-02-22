<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Models\WorkShift;
use App\Models\WorkShiftAssignment;
use App\Services\Cache\CacheVersionService;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('create/update/delete jogosultság nélkül tiltott', function (): void {
    $company = Company::factory()->create();
    $schedule = WorkSchedule::factory()->create(['company_id' => $company->id, 'status' => 'draft']);
    $employee = Employee::factory()->create(['company_id' => $company->id]);
    $shift = WorkShift::factory()->create(['company_id' => $company->id]);

    /** @var User $user */
    $user = User::factory()->create();
    $user->assignRole('user');

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $payload = [
        'work_schedule_id' => $schedule->id,
        'employee_id' => $employee->id,
        'work_shift_id' => $shift->id,
        'date' => '2026-07-10',
    ];

    $this->actingAs($user)
        ->withSession(['current_company_id' => $company->id])
        ->postJson(route('work_schedule_assignments.store'), $payload)
        ->assertForbidden();
});

it('tiltja a schedule intervallumon kívüli dátumot', function (): void {
    $user = $this->createAdminUser();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $company = Company::factory()->create();
    $schedule = WorkSchedule::factory()->create([
        'company_id' => $company->id,
        'date_from' => '2026-08-01',
        'date_to' => '2026-08-31',
        'status' => 'draft',
    ]);
    $employee = Employee::factory()->create(['company_id' => $company->id]);
    $shift = WorkShift::factory()->create(['company_id' => $company->id]);

    $this->actingAs($user)
        ->withSession(['current_company_id' => $company->id])
        ->postJson(route('work_schedule_assignments.store'), [
            'work_schedule_id' => $schedule->id,
            'employee_id' => $employee->id,
            'work_shift_id' => $shift->id,
            'date' => '2026-09-01',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['date']);
});

it('published schedule lock: planner műveletek tiltva', function (): void {
    $user = $this->createAdminUser();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $company = Company::factory()->create();
    $schedule = WorkSchedule::factory()->create([
        'company_id' => $company->id,
        'date_from' => '2026-09-01',
        'date_to' => '2026-09-30',
        'status' => 'published',
    ]);
    $employee = Employee::factory()->create(['company_id' => $company->id]);
    $shift = WorkShift::factory()->create(['company_id' => $company->id]);

    $this->actingAs($user)
        ->withSession(['current_company_id' => $company->id])
        ->postJson(route('work_schedule_assignments.store'), [
            'work_schedule_id' => $schedule->id,
            'employee_id' => $employee->id,
            'work_shift_id' => $shift->id,
            'date' => '2026-09-10',
        ])
        ->assertForbidden();
});

it('egy dolgozónak egy napra csak egy műszak lehet (unique)', function (): void {
    $user = $this->createAdminUser();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $company = Company::factory()->create();
    $schedule = WorkSchedule::factory()->create([
        'company_id' => $company->id,
        'date_from' => '2026-10-01',
        'date_to' => '2026-10-31',
        'status' => 'draft',
    ]);
    $employee = Employee::factory()->create(['company_id' => $company->id]);
    $shiftA = WorkShift::factory()->create(['company_id' => $company->id]);
    $shiftB = WorkShift::factory()->create(['company_id' => $company->id]);

    $this->actingAs($user)
        ->withSession(['current_company_id' => $company->id])
        ->postJson(route('work_schedule_assignments.store'), [
            'work_schedule_id' => $schedule->id,
            'employee_id' => $employee->id,
            'work_shift_id' => $shiftA->id,
            'date' => '2026-10-12',
        ])
        ->assertCreated();

    $this->actingAs($user)
        ->withSession(['current_company_id' => $company->id])
        ->postJson(route('work_schedule_assignments.store'), [
            'work_schedule_id' => $schedule->id,
            'employee_id' => $employee->id,
            'work_shift_id' => $shiftB->id,
            'date' => '2026-10-12',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['employee_id']);
});

it('cache bump store/update/delete után', function (): void {
    $user = $this->createAdminUser();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $company = Company::factory()->create();
    $schedule = WorkSchedule::factory()->create([
        'company_id' => $company->id,
        'date_from' => '2026-11-01',
        'date_to' => '2026-11-30',
        'status' => 'draft',
    ]);
    $employee = Employee::factory()->create(['company_id' => $company->id]);
    $shiftA = WorkShift::factory()->create(['company_id' => $company->id]);
    $shiftB = WorkShift::factory()->create(['company_id' => $company->id]);

    $versioner = app(CacheVersionService::class);
    Cache::forever("v:company:{$company->id}:work_schedule_assignments", 1);

    $createResponse = $this->actingAs($user)
        ->withSession(['current_company_id' => $company->id])
        ->postJson(route('work_schedule_assignments.store'), [
            'work_schedule_id' => $schedule->id,
            'employee_id' => $employee->id,
            'work_shift_id' => $shiftA->id,
            'date' => '2026-11-12',
        ])
        ->assertCreated();

    expect($versioner->get("company:{$company->id}:work_schedule_assignments"))->toBe(2);

    $id = (int) $createResponse->json('data.id');

    $this->actingAs($user)
        ->withSession(['current_company_id' => $company->id])
        ->putJson(route('work_schedule_assignments.update', ['id' => $id]), [
            'work_schedule_id' => $schedule->id,
            'employee_id' => $employee->id,
            'work_shift_id' => $shiftB->id,
            'date' => '2026-11-13',
        ])
        ->assertOk();

    expect($versioner->get("company:{$company->id}:work_schedule_assignments"))->toBe(3);

    $this->actingAs($user)
        ->withSession(['current_company_id' => $company->id])
        ->deleteJson(route('work_schedule_assignments.destroy', ['id' => $id]))
        ->assertOk();

    expect($versioner->get("company:{$company->id}:work_schedule_assignments"))->toBe(4);
});

