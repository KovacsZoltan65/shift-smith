<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Models\WorkShift;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('requires selected company context for work schedule assignment routes', function (): void {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    /** @var User $user */
    $user = $this->createAdminUser($companyA);
    $user->companies()->syncWithoutDetaching([$companyB->id]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $this->actingAs($user)
        ->get(route('scheduling.calendar'))
        ->assertRedirect(route('company.select'));
});

it('does not leak assignment data between companies', function (): void {
    [, $companyA] = $this->createTenantWithCompany();
    [, $companyB] = $this->createTenantWithCompany();

    $user = $this->createAdminUser($companyA);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $scheduleB = WorkSchedule::factory()->create([
        'company_id' => $companyB->id,
        'status' => 'draft',
    ]);

    $this->actingAsUserInCompany($user, $companyA)
        ->getJson(route('scheduling.calendar.feed', [
            'schedule_id' => $scheduleB->id,
            'view_type' => 'week',
            'week_count' => 1,
        ]))
        ->assertNotFound();
});

it('blocks writes to another company scope', function (): void {
    [, $companyA] = $this->createTenantWithCompany();
    [, $companyB] = $this->createTenantWithCompany();

    $user = $this->createAdminUser($companyA);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $scheduleB = WorkSchedule::factory()->create([
        'company_id' => $companyB->id,
        'status' => 'draft',
    ]);
    $employeeB = Employee::factory()->create(['company_id' => $companyB->id]);
    $shiftB = WorkShift::factory()->create(['company_id' => $companyB->id]);

    $this->actingAsUserInCompany($user, $companyA)
        ->postJson(route('work_schedule_assignments.store'), [
            'work_schedule_id' => $scheduleB->id,
            'employee_id' => $employeeB->id,
            'work_shift_id' => $shiftB->id,
            'date' => '2027-01-10',
        ])
        ->assertNotFound();

    $this->assertDatabaseMissing('work_shift_assignments', [
        'company_id' => $companyA->id,
        'work_schedule_id' => $scheduleB->id,
        'employee_id' => $employeeB->id,
        'work_shift_id' => $shiftB->id,
        'date' => '2027-01-10',
    ]);
});
