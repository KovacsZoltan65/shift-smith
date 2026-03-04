<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Employee;
use App\Models\MonthClosure;
use App\Models\TenantGroup;
use App\Models\WorkSchedule;
use App\Models\WorkShift;
use App\Models\WorkShiftAssignment;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-03-15'));
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

it('closed month blocks assignment create, update, delete és bulk upsert 403-mal', function (): void {
    [, $company] = $this->createTenantWithCompany();
    $admin = $this->createAdminUser($company);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $admin->refresh();

    $schedule = WorkSchedule::factory()->create([
        'company_id' => $company->id,
        'date_from' => '2026-03-01',
        'date_to' => '2026-03-31',
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
        'date' => '2026-03-20',
    ]);

    MonthClosure::factory()->create([
        'company_id' => $company->id,
        'year' => 2026,
        'month' => 3,
        'closed_by_user_id' => $admin->id,
    ]);

    $expected = 'A(z) 2026-03 hónap lezárva, a szerkesztés nem engedélyezett.';

    $this->actingAsUserInCompany($admin, $company)
        ->postJson(route('work_schedule_assignments.store'), [
            'work_schedule_id' => $schedule->id,
            'employee_id' => $employee->id,
            'work_shift_id' => $shiftA->id,
            'date' => '2026-03-25',
        ])
        ->assertForbidden()
        ->assertJsonPath('message', $expected);

    $this->actingAsUserInCompany($admin, $company)
        ->putJson(route('work_schedule_assignments.update', ['id' => $assignment->id]), [
            'work_schedule_id' => $schedule->id,
            'employee_id' => $employee->id,
            'work_shift_id' => $shiftB->id,
            'date' => '2026-03-26',
        ])
        ->assertForbidden()
        ->assertJsonPath('message', $expected);

    $this->actingAsUserInCompany($admin, $company)
        ->deleteJson(route('work_schedule_assignments.destroy', ['id' => $assignment->id]))
        ->assertForbidden()
        ->assertJsonPath('message', $expected);

    $this->actingAsUserInCompany($admin, $company)
        ->postJson(route('work_schedule_assignments.bulk_upsert'), [
            'work_schedule_id' => $schedule->id,
            'work_shift_id' => $shiftB->id,
            'employee_ids' => [$employee->id],
            'dates' => ['2026-03-27'],
        ])
        ->assertForbidden()
        ->assertJsonPath('message', $expected);
});

it('open month allows assignment update', function (): void {
    [, $company] = $this->createTenantWithCompany();
    $admin = $this->createAdminUser($company);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $admin->refresh();

    $schedule = WorkSchedule::factory()->create([
        'company_id' => $company->id,
        'date_from' => '2026-04-01',
        'date_to' => '2026-04-30',
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
        'date' => '2026-04-20',
    ]);

    $this->actingAsUserInCompany($admin, $company)
        ->putJson(route('work_schedule_assignments.update', ['id' => $assignment->id]), [
            'work_schedule_id' => $schedule->id,
            'employee_id' => $employee->id,
            'work_shift_id' => $shiftB->id,
            'date' => '2026-04-21',
        ])
        ->assertOk()
        ->assertJsonPath('data.work_shift_id', $shiftB->id);
});

it('más tenant closure-je nem blokkolja a current tenant módosítását', function (): void {
    [, $companyA] = $this->createTenantWithCompany();
    [, $companyB] = $this->createTenantWithCompany();
    $admin = $this->createAdminUser($companyA);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $admin->refresh();

    $schedule = WorkSchedule::factory()->create([
        'company_id' => $companyA->id,
        'date_from' => '2026-03-01',
        'date_to' => '2026-03-31',
        'status' => 'draft',
    ]);
    $employee = Employee::factory()->create(['company_id' => $companyA->id]);
    $shift = WorkShift::factory()->create(['company_id' => $companyA->id]);

    MonthClosure::factory()->create([
        'company_id' => $companyB->id,
        'year' => 2026,
        'month' => 3,
    ]);

    $this->actingAsUserInCompany($admin, $companyA)
        ->postJson(route('work_schedule_assignments.store'), [
            'work_schedule_id' => $schedule->id,
            'employee_id' => $employee->id,
            'work_shift_id' => $shift->id,
            'date' => '2026-03-18',
        ])
        ->assertCreated();
});

it('más company closure-je ugyanazon tenantben nem blokkolja a módosítást', function (): void {
    $tenant = TenantGroup::factory()->create();
    $companyA = Company::factory()->create(['tenant_group_id' => $tenant->id]);
    $companyB = Company::factory()->create(['tenant_group_id' => $tenant->id]);

    $admin = $this->createAdminUser($companyA);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $admin->refresh();

    $schedule = WorkSchedule::factory()->create([
        'company_id' => $companyA->id,
        'date_from' => '2026-03-01',
        'date_to' => '2026-03-31',
        'status' => 'draft',
    ]);
    $employee = Employee::factory()->create(['company_id' => $companyA->id]);
    $shift = WorkShift::factory()->create(['company_id' => $companyA->id]);

    MonthClosure::factory()->create([
        'company_id' => $companyB->id,
        'year' => 2026,
        'month' => 3,
    ]);

    $this->actingAsUserInCompany($admin, $companyA)
        ->postJson(route('work_schedule_assignments.store'), [
            'work_schedule_id' => $schedule->id,
            'employee_id' => $employee->id,
            'work_shift_id' => $shift->id,
            'date' => '2026-03-19',
        ])
        ->assertCreated();
});

it('calendar page és feed átadja a month lock állapotot', function (): void {
    [, $company] = $this->createTenantWithCompany();
    $admin = $this->createAdminUser($company);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $admin->refresh();

    $schedule = WorkSchedule::factory()->create([
        'company_id' => $company->id,
        'date_from' => '2026-03-01',
        'date_to' => '2026-03-31',
        'status' => 'draft',
    ]);

    MonthClosure::factory()->create([
        'company_id' => $company->id,
        'year' => 2026,
        'month' => 3,
        'closed_by_user_id' => $admin->id,
        'note' => 'Lezárva',
    ]);

    $this->actingAsUserInCompany($admin, $company)
        ->get(route('scheduling.calendar'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Scheduling/Calendar/Index')
            ->where('month_lock.is_closed', true)
            ->where('permissions.monthClosureClose', true)
            ->where('permissions.monthClosureReopen', true)
        );

    $this->actingAsUserInCompany($admin, $company)
        ->getJson(route('scheduling.calendar.feed', [
            'schedule_id' => $schedule->id,
            'view_type' => 'month',
            'month' => 3,
            'year' => 2026,
        ]))
        ->assertOk()
        ->assertJsonPath('meta.month_lock.is_closed', true)
        ->assertJsonPath('meta.month_lock.note', 'Lezárva')
        ->assertJsonPath('meta.closed_month_keys.0', '2026-03');
});
