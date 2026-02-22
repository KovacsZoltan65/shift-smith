<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Models\WorkShift;
use App\Models\WorkShiftAssignment;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('megtagadja a feed végpontot jogosultság nélkül', function (): void {
    $company = Company::factory()->create();
    $schedule = WorkSchedule::factory()->create(['company_id' => $company->id]);

    /** @var User $user */
    $user = User::factory()->create();
    $user->assignRole('user');

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $this->actingAs($user)
        ->withSession(['current_company_id' => $company->id])
        ->getJson(route('scheduling.calendar.feed', ['schedule_id' => $schedule->id]))
        ->assertForbidden();
});

it('engedélyezi a feed végpontot és visszaadja az eseményeket', function (): void {
    $user = $this->createAdminUser();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $company = Company::factory()->create();
    $schedule = WorkSchedule::factory()->create([
        'company_id' => $company->id,
        'date_from' => '2026-06-01',
        'date_to' => '2026-06-30',
    ]);
    $employee = Employee::factory()->create(['company_id' => $company->id]);
    $shift = WorkShift::factory()->create(['company_id' => $company->id, 'name' => 'Délelőttös']);

    WorkShiftAssignment::factory()->create([
        'company_id' => $company->id,
        'work_schedule_id' => $schedule->id,
        'employee_id' => $employee->id,
        'work_shift_id' => $shift->id,
        'date' => '2026-06-10',
    ]);

    $this->actingAs($user)
        ->withSession(['current_company_id' => $company->id])
        ->getJson(route('scheduling.calendar.feed', [
            'schedule_id' => $schedule->id,
            'start' => '2026-06-01',
            'end' => '2026-06-30',
        ]))
        ->assertOk()
        ->assertJsonStructure(['data' => [['id', 'title', 'start', 'end', 'allDay', 'extendedProps']]]);
});

it('tenant izolált feed: másik cég schedule-je nem kérhető le', function (): void {
    $user = $this->createAdminUser();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $scheduleB = WorkSchedule::factory()->create(['company_id' => $companyB->id]);

    $this->actingAs($user)
        ->withSession(['current_company_id' => $companyA->id])
        ->getJson(route('scheduling.calendar.feed', ['schedule_id' => $scheduleB->id]))
        ->assertNotFound();
});

