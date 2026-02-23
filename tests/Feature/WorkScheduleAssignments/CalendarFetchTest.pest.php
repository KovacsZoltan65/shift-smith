<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Employee;
use App\Models\Position;
use App\Models\WorkSchedule;
use App\Models\WorkShift;
use App\Models\WorkShiftAssignment;
use Carbon\CarbonImmutable;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('calendar feed heti nézetben működik', function (): void {
    $user = $this->createAdminUser();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $today = CarbonImmutable::today();
    $weekStart = $today->startOfWeek();

    $company = Company::factory()->create();
    $schedule = WorkSchedule::factory()->create([
        'company_id' => $company->id,
        'date_from' => $weekStart->subWeeks(2)->toDateString(),
        'date_to' => $weekStart->addWeeks(2)->toDateString(),
        'status' => 'draft',
    ]);
    $employee = Employee::factory()->create(['company_id' => $company->id]);
    $shift = WorkShift::factory()->create(['company_id' => $company->id]);

    WorkShiftAssignment::factory()->create([
        'company_id' => $company->id,
        'work_schedule_id' => $schedule->id,
        'employee_id' => $employee->id,
        'work_shift_id' => $shift->id,
        'date' => $weekStart->toDateString(),
    ]);

    $this->actingAs($user)
        ->withSession(['current_company_id' => $company->id])
        ->getJson(route('scheduling.calendar.feed', [
            'schedule_id' => $schedule->id,
            'view_type' => 'week',
            'week_count' => 1,
        ]))
        ->assertOk()
        ->assertJsonPath('meta.range.start', $weekStart->toDateString())
        ->assertJsonStructure([
            'data' => [['id', 'editable']],
            'meta' => ['range' => ['start', 'end'], 'selected_date', 'editable'],
        ]);
});

it('calendar feed havi nézetben működik', function (): void {
    $user = $this->createAdminUser();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $today = CarbonImmutable::today();
    $company = Company::factory()->create();
    $schedule = WorkSchedule::factory()->create([
        'company_id' => $company->id,
        'date_from' => $today->startOfMonth()->toDateString(),
        'date_to' => $today->endOfMonth()->toDateString(),
        'status' => 'draft',
    ]);
    $employee = Employee::factory()->create(['company_id' => $company->id]);
    $shift = WorkShift::factory()->create(['company_id' => $company->id]);

    WorkShiftAssignment::factory()->create([
        'company_id' => $company->id,
        'work_schedule_id' => $schedule->id,
        'employee_id' => $employee->id,
        'work_shift_id' => $shift->id,
        'date' => $today->toDateString(),
    ]);

    $this->actingAs($user)
        ->withSession(['current_company_id' => $company->id])
        ->getJson(route('scheduling.calendar.feed', [
            'schedule_id' => $schedule->id,
            'view_type' => 'month',
            'month' => (int) $today->format('m'),
            'year' => (int) $today->format('Y'),
        ]))
        ->assertOk()
        ->assertJsonPath('meta.range.start', $today->startOfMonth()->toDateString())
        ->assertJsonPath('meta.range.end', $today->endOfMonth()->toDateString());
});

it('calendar feed napi nézetben működik', function (): void {
    $user = $this->createAdminUser();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $today = CarbonImmutable::today();
    $company = Company::factory()->create();
    $schedule = WorkSchedule::factory()->create([
        'company_id' => $company->id,
        'date_from' => $today->subDays(1)->toDateString(),
        'date_to' => $today->addDays(1)->toDateString(),
        'status' => 'draft',
    ]);
    $employee = Employee::factory()->create(['company_id' => $company->id]);
    $shift = WorkShift::factory()->create(['company_id' => $company->id]);

    WorkShiftAssignment::factory()->create([
        'company_id' => $company->id,
        'work_schedule_id' => $schedule->id,
        'employee_id' => $employee->id,
        'work_shift_id' => $shift->id,
        'date' => $today->toDateString(),
    ]);

    $this->actingAs($user)
        ->withSession(['current_company_id' => $company->id])
        ->getJson(route('scheduling.calendar.feed', [
            'schedule_id' => $schedule->id,
            'view_type' => 'day',
            'date' => $today->toDateString(),
        ]))
        ->assertOk()
        ->assertJsonPath('meta.range.start', $today->toDateString())
        ->assertJsonPath('meta.range.end', $today->toDateString());
});

it('calendar feed tenant izolált', function (): void {
    $user = $this->createAdminUser();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $scheduleB = WorkSchedule::factory()->create(['company_id' => $companyB->id]);

    $this->actingAs($user)
        ->withSession(['current_company_id' => $companyA->id])
        ->getJson(route('scheduling.calendar.feed', [
            'schedule_id' => $scheduleB->id,
            'view_type' => 'week',
            'week_count' => 1,
        ]))
        ->assertNotFound();
});

it('calendar feed heti nézetben alkalmazza a szűrőket', function (): void {
    $user = $this->createAdminUser();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $today = CarbonImmutable::today();
    $weekStart = $today->startOfWeek();

    $company = Company::factory()->create();
    $positionA = Position::factory()->create(['company_id' => $company->id]);
    $positionB = Position::factory()->create(['company_id' => $company->id]);
    $employeeA = Employee::factory()->create(['company_id' => $company->id, 'position_id' => $positionA->id]);
    $employeeB = Employee::factory()->create(['company_id' => $company->id, 'position_id' => $positionA->id]);
    $employeeC = Employee::factory()->create(['company_id' => $company->id, 'position_id' => $positionB->id]);
    $shiftA = WorkShift::factory()->create(['company_id' => $company->id]);
    $shiftB = WorkShift::factory()->create(['company_id' => $company->id]);

    $schedule = WorkSchedule::factory()->create([
        'company_id' => $company->id,
        'date_from' => $weekStart->toDateString(),
        'date_to' => $weekStart->endOfWeek()->toDateString(),
        'status' => 'draft',
    ]);

    $match = WorkShiftAssignment::factory()->create([
        'company_id' => $company->id,
        'work_schedule_id' => $schedule->id,
        'employee_id' => $employeeA->id,
        'work_shift_id' => $shiftA->id,
        'date' => $weekStart->toDateString(),
    ]);

    WorkShiftAssignment::factory()->create([
        'company_id' => $company->id,
        'work_schedule_id' => $schedule->id,
        'employee_id' => $employeeB->id,
        'work_shift_id' => $shiftA->id,
        'date' => $weekStart->addDay()->toDateString(),
    ]);

    WorkShiftAssignment::factory()->create([
        'company_id' => $company->id,
        'work_schedule_id' => $schedule->id,
        'employee_id' => $employeeA->id,
        'work_shift_id' => $shiftB->id,
        'date' => $weekStart->addDays(2)->toDateString(),
    ]);

    WorkShiftAssignment::factory()->create([
        'company_id' => $company->id,
        'work_schedule_id' => $schedule->id,
        'employee_id' => $employeeC->id,
        'work_shift_id' => $shiftA->id,
        'date' => $weekStart->addDays(3)->toDateString(),
    ]);

    $this->actingAs($user)
        ->withSession(['current_company_id' => $company->id])
        ->getJson(route('scheduling.calendar.feed', [
            'schedule_id' => $schedule->id,
            'view_type' => 'week',
            'week_count' => 1,
            'employee_ids' => [$employeeA->id],
            'work_shift_ids' => [$shiftA->id],
            'position_ids' => [$positionA->id],
        ]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $match->id);
});

it('calendar feed havi nézetben alkalmazza a szűrőket', function (): void {
    $user = $this->createAdminUser();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $today = CarbonImmutable::today();
    $monthStart = $today->startOfMonth();

    $company = Company::factory()->create();
    $positionA = Position::factory()->create(['company_id' => $company->id]);
    $positionB = Position::factory()->create(['company_id' => $company->id]);
    $employeeA = Employee::factory()->create(['company_id' => $company->id, 'position_id' => $positionA->id]);
    $employeeB = Employee::factory()->create(['company_id' => $company->id, 'position_id' => $positionB->id]);
    $shiftA = WorkShift::factory()->create(['company_id' => $company->id]);
    $shiftB = WorkShift::factory()->create(['company_id' => $company->id]);

    $schedule = WorkSchedule::factory()->create([
        'company_id' => $company->id,
        'date_from' => $monthStart->toDateString(),
        'date_to' => $monthStart->endOfMonth()->toDateString(),
        'status' => 'draft',
    ]);

    $match = WorkShiftAssignment::factory()->create([
        'company_id' => $company->id,
        'work_schedule_id' => $schedule->id,
        'employee_id' => $employeeA->id,
        'work_shift_id' => $shiftA->id,
        'date' => $monthStart->toDateString(),
    ]);

    WorkShiftAssignment::factory()->create([
        'company_id' => $company->id,
        'work_schedule_id' => $schedule->id,
        'employee_id' => $employeeA->id,
        'work_shift_id' => $shiftB->id,
        'date' => $monthStart->addDay()->toDateString(),
    ]);

    WorkShiftAssignment::factory()->create([
        'company_id' => $company->id,
        'work_schedule_id' => $schedule->id,
        'employee_id' => $employeeB->id,
        'work_shift_id' => $shiftA->id,
        'date' => $monthStart->addDays(2)->toDateString(),
    ]);

    $this->actingAs($user)
        ->withSession(['current_company_id' => $company->id])
        ->getJson(route('scheduling.calendar.feed', [
            'schedule_id' => $schedule->id,
            'view_type' => 'month',
            'month' => (int) $monthStart->format('m'),
            'year' => (int) $monthStart->format('Y'),
            'employee_ids' => [$employeeA->id],
            'work_shift_ids' => [$shiftA->id],
            'position_ids' => [$positionA->id],
        ]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $match->id);
});

it('calendar feed napi nézetben alkalmazza a szűrőket', function (): void {
    $user = $this->createAdminUser();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $today = CarbonImmutable::today();

    $company = Company::factory()->create();
    $positionA = Position::factory()->create(['company_id' => $company->id]);
    $positionB = Position::factory()->create(['company_id' => $company->id]);
    $employeeA = Employee::factory()->create(['company_id' => $company->id, 'position_id' => $positionA->id]);
    $employeeC = Employee::factory()->create(['company_id' => $company->id, 'position_id' => $positionA->id]);
    $employeeB = Employee::factory()->create(['company_id' => $company->id, 'position_id' => $positionB->id]);
    $shiftA = WorkShift::factory()->create(['company_id' => $company->id]);
    $shiftB = WorkShift::factory()->create(['company_id' => $company->id]);

    $schedule = WorkSchedule::factory()->create([
        'company_id' => $company->id,
        'date_from' => $today->toDateString(),
        'date_to' => $today->toDateString(),
        'status' => 'draft',
    ]);

    $match = WorkShiftAssignment::factory()->create([
        'company_id' => $company->id,
        'work_schedule_id' => $schedule->id,
        'employee_id' => $employeeA->id,
        'work_shift_id' => $shiftA->id,
        'date' => $today->toDateString(),
    ]);

    WorkShiftAssignment::factory()->create([
        'company_id' => $company->id,
        'work_schedule_id' => $schedule->id,
        'employee_id' => $employeeC->id,
        'work_shift_id' => $shiftB->id,
        'date' => $today->toDateString(),
    ]);

    WorkShiftAssignment::factory()->create([
        'company_id' => $company->id,
        'work_schedule_id' => $schedule->id,
        'employee_id' => $employeeB->id,
        'work_shift_id' => $shiftA->id,
        'date' => $today->toDateString(),
    ]);

    $this->actingAs($user)
        ->withSession(['current_company_id' => $company->id])
        ->getJson(route('scheduling.calendar.feed', [
            'schedule_id' => $schedule->id,
            'view_type' => 'day',
            'date' => $today->toDateString(),
            'employee_ids' => [$employeeA->id],
            'work_shift_ids' => [$shiftA->id],
            'position_ids' => [$positionA->id],
        ]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $match->id);
});

it('calendar feed heti nézetben elfogadja a hétszámot', function (): void {
    $user = $this->createAdminUser();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $today = CarbonImmutable::today();
    $weekStart = $today->startOfWeek();

    $company = Company::factory()->create();
    $schedule = WorkSchedule::factory()->create([
        'company_id' => $company->id,
        'date_from' => $weekStart->subWeeks(1)->toDateString(),
        'date_to' => $weekStart->addWeeks(1)->toDateString(),
        'status' => 'draft',
    ]);
    $employee = Employee::factory()->create(['company_id' => $company->id]);
    $shift = WorkShift::factory()->create(['company_id' => $company->id]);

    WorkShiftAssignment::factory()->create([
        'company_id' => $company->id,
        'work_schedule_id' => $schedule->id,
        'employee_id' => $employee->id,
        'work_shift_id' => $shift->id,
        'date' => $weekStart->toDateString(),
    ]);

    $this->actingAs($user)
        ->withSession(['current_company_id' => $company->id])
        ->getJson(route('scheduling.calendar.feed', [
            'schedule_id' => $schedule->id,
            'view_type' => 'week',
            'week_number' => (int) $today->isoWeek(),
            'week_year' => (int) $today->format('Y'),
        ]))
        ->assertOk()
        ->assertJsonPath('meta.range.start', $weekStart->toDateString())
        ->assertJsonPath('meta.range.end', $weekStart->endOfWeek()->toDateString())
        ->assertJsonCount(1, 'data');
});
