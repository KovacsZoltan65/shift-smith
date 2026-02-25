<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Employee;
use App\Models\TenantGroup;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Models\WorkShift;
use App\Models\WorkShiftAssignment;
use App\Services\Cache\CacheNamespaces;
use App\Services\Cache\CacheVersionService;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('csak a work schedule intervallumán belüli assignmentet engedi', function (): void {
    $tenant = TenantGroup::factory()->create();
    $company = Company::factory()->create(['tenant_group_id' => $tenant->id]);
    $user = $this->createAdminUser($company);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();
    $shift = WorkShift::factory()->create(['company_id' => $company->id]);
    $employee = Employee::factory()->create(['company_id' => $company->id]);
    $schedule = WorkSchedule::factory()->create([
        'company_id' => $company->id,
        'date_from' => '2026-01-01',
        'date_to' => '2026-01-31',
        'status' => 'draft',
    ]);

    $this->actingAs($user)
        ->withSession(['current_company_id' => $company->id, 'current_tenant_group_id' => $tenant->id])
        ->postJson(route('work_shift_assignments.store', ['work_shift' => $shift->id]), [
            'employee_id' => $employee->id,
            'work_schedule_id' => $schedule->id,
            'date' => '2026-02-10',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['date']);
});

it('egy dolgozónak egy napra csak egy műszak assignmentje marad', function (): void {
    $tenant = TenantGroup::factory()->create();
    $company = Company::factory()->create(['tenant_group_id' => $tenant->id]);
    $user = $this->createAdminUser($company);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();
    $shiftA = WorkShift::factory()->create(['company_id' => $company->id]);
    $shiftB = WorkShift::factory()->create(['company_id' => $company->id]);
    $employee = Employee::factory()->create(['company_id' => $company->id]);
    $schedule = WorkSchedule::factory()->create([
        'company_id' => $company->id,
        'date_from' => '2026-03-01',
        'date_to' => '2026-03-31',
        'status' => 'draft',
    ]);

    $date = '2026-03-10';

    $first = $this->actingAs($user)
        ->withSession(['current_company_id' => $company->id, 'current_tenant_group_id' => $tenant->id])
        ->postJson(route('work_shift_assignments.store', ['work_shift' => $shiftA->id]), [
            'employee_id' => $employee->id,
            'work_schedule_id' => $schedule->id,
            'date' => $date,
        ])
        ->assertCreated()
        ->json('data.id');

    $second = $this->actingAs($user)
        ->withSession(['current_company_id' => $company->id, 'current_tenant_group_id' => $tenant->id])
        ->postJson(route('work_shift_assignments.store', ['work_shift' => $shiftB->id]), [
            'employee_id' => $employee->id,
            'work_schedule_id' => $schedule->id,
            'date' => $date,
        ])
        ->assertCreated()
        ->json('data.id');

    expect((int) $first)->toBe((int) $second);

    expect(WorkShiftAssignment::query()
        ->where('company_id', $company->id)
        ->where('employee_id', $employee->id)
        ->whereDate('date', $date)
        ->count())->toBe(1);

    $this->assertDatabaseHas('work_shift_assignments', [
        'id' => $second,
        'work_shift_id' => $shiftB->id,
    ]);
});

it('megtagadja az assignment létrehozást jogosultság nélkül', function (): void {
    $tenant = TenantGroup::factory()->create();
    $company = Company::factory()->create(['tenant_group_id' => $tenant->id]);
    $shift = WorkShift::factory()->create(['company_id' => $company->id]);
    $employee = Employee::factory()->create(['company_id' => $company->id]);
    $schedule = WorkSchedule::factory()->create([
        'company_id' => $company->id,
        'date_from' => '2026-04-01',
        'date_to' => '2026-04-30',
        'status' => 'draft',
    ]);

    /** @var User $user */
    $user = User::factory()->create();
    $user->assignRole('user');

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $this->actingAs($user)
        ->withSession(['current_company_id' => $company->id, 'current_tenant_group_id' => $tenant->id])
        ->postJson(route('work_shift_assignments.store', ['work_shift' => $shift->id]), [
            'employee_id' => $employee->id,
            'work_schedule_id' => $schedule->id,
            'date' => '2026-04-10',
        ])
        ->assertForbidden();
});

it('assignment store és destroy után bumpolja a cache verziót', function (): void {
    $tenant = TenantGroup::factory()->create();
    $company = Company::factory()->create(['tenant_group_id' => $tenant->id]);
    $user = $this->createAdminUser($company);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();
    $shift = WorkShift::factory()->create(['company_id' => $company->id]);
    $employee = Employee::factory()->create(['company_id' => $company->id]);
    $schedule = WorkSchedule::factory()->create([
        'company_id' => $company->id,
        'date_from' => '2026-05-01',
        'date_to' => '2026-05-31',
        'status' => 'draft',
    ]);

    $versioner = app(CacheVersionService::class);
    $namespace = CacheNamespaces::tenantWorkScheduleAssignments($tenant->id);
    Cache::forever("v:{$namespace}", 1);

    $createResponse = $this->actingAs($user)
        ->withSession(['current_company_id' => $company->id, 'current_tenant_group_id' => $tenant->id])
        ->postJson(route('work_shift_assignments.store', ['work_shift' => $shift->id]), [
            'employee_id' => $employee->id,
            'work_schedule_id' => $schedule->id,
            'date' => '2026-05-10',
        ])
        ->assertCreated();

    expect($versioner->get($namespace))->toBe(2);

    $assignmentId = (int) $createResponse->json('data.id');

    $this->actingAs($user)
        ->withSession(['current_company_id' => $company->id, 'current_tenant_group_id' => $tenant->id])
        ->deleteJson(route('work_shift_assignments.destroy', [
            'work_shift' => $shift->id,
            'id' => $assignmentId,
        ]))
        ->assertOk();

    expect($versioner->get($namespace))->toBe(3);
});
