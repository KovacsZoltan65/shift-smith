<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Employee;
use App\Models\TenantGroup;
use App\Models\WorkShift;
use App\Models\WorkShiftAssignment;
use Carbon\CarbonImmutable;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('skips weekend planning when weekend policy is skip and weekdays are monday-friday', function (): void {
    $tenantOne = TenantGroup::factory()->create();
    $tenantTwo = TenantGroup::factory()->create();

    $companyA = Company::factory()->create(['tenant_group_id' => $tenantOne->id, 'active' => true]);
    $companyB = Company::factory()->create(['tenant_group_id' => $tenantOne->id, 'active' => true]);
    $companyC = Company::factory()->create(['tenant_group_id' => $tenantTwo->id, 'active' => true]);

    $user = $this->createAdminUser($companyA);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();
    $user->givePermissionTo('work_schedules.autoplan');

    $employeesA = Employee::factory()->count(3)->create(['company_id' => $companyA->id, 'active' => true]);
    Employee::factory()->create(['company_id' => $companyB->id, 'active' => true]);
    Employee::factory()->create(['company_id' => $companyC->id, 'active' => true]);

    $shiftA = WorkShift::factory()->create([
        'company_id' => $companyA->id,
        'start_time' => '08:00:00',
        'end_time' => '16:00:00',
        'active' => true,
    ]);

    CompanySetting::query()->create([
        'company_id' => $companyA->id,
        'key' => 'planning.allowed_weekdays',
        'value' => [1, 2, 3, 4, 5],
        'updated_by' => (int) $user->id,
    ]);
    CompanySetting::query()->create([
        'company_id' => $companyA->id,
        'key' => 'autoplan.weekend_policy',
        'value' => 'skip',
        'updated_by' => (int) $user->id,
    ]);

    $payload = [
        'month' => '2026-04',
        'employee_ids' => $employeesA->pluck('id')->map(fn ($id): int => (int) $id)->values()->all(),
        'demand' => [
            'weekday' => [
                ['shift_id' => (int) $shiftA->id, 'required_count' => 2],
            ],
            'weekend' => [],
        ],
        'rules' => [
            'min_rest_hours' => 0,
            'max_consecutive_days' => 31,
            'weekend_fairness' => true,
        ],
    ];

    $response = $this->actingAsUserInCompany($user, $companyA)
        ->postJson(route('scheduling.work_schedules.autoplan.generate'), $payload)
        ->assertCreated()
        ->assertJsonPath('data.coverage.slots_missing', 0);

    $scheduleId = (int) $response->json('data.work_schedule.id');

    $assignments = WorkShiftAssignment::query()
        ->where('company_id', $companyA->id)
        ->where('work_schedule_id', $scheduleId)
        ->get();

    expect($assignments)->not->toBeEmpty();

    foreach ($assignments as $assignment) {
        $iso = (int) CarbonImmutable::parse((string) $assignment->date)->dayOfWeekIso;
        expect($iso)->toBeGreaterThanOrEqual(1)->toBeLessThanOrEqual(5);
    }

    $weekdayCount = 0;
    $cursor = CarbonImmutable::create(2026, 4, 1)->startOfMonth();
    $end = $cursor->endOfMonth();
    while ($cursor->lessThanOrEqualTo($end)) {
        if ($cursor->dayOfWeekIso <= 5) {
            $weekdayCount++;
        }
        $cursor = $cursor->addDay();
    }

    expect($assignments->count())->toBe($weekdayCount * 2);
});

it('plans on weekend when weekend policy is require_if_demand and weekend demand is present', function (): void {
    $tenantOne = TenantGroup::factory()->create();
    $tenantTwo = TenantGroup::factory()->create();

    $companyA = Company::factory()->create(['tenant_group_id' => $tenantOne->id, 'active' => true]);
    $companyB = Company::factory()->create(['tenant_group_id' => $tenantOne->id, 'active' => true]);
    $companyC = Company::factory()->create(['tenant_group_id' => $tenantTwo->id, 'active' => true]);

    $user = $this->createAdminUser($companyB);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();
    $user->givePermissionTo('work_schedules.autoplan');

    $employeesB = Employee::factory()->count(2)->create(['company_id' => $companyB->id, 'active' => true]);
    Employee::factory()->count(2)->create(['company_id' => $companyA->id, 'active' => true]);
    Employee::factory()->count(2)->create(['company_id' => $companyC->id, 'active' => true]);

    $shiftB = WorkShift::factory()->create([
        'company_id' => $companyB->id,
        'start_time' => '08:00:00',
        'end_time' => '16:00:00',
        'active' => true,
    ]);

    CompanySetting::query()->create([
        'company_id' => $companyB->id,
        'key' => 'planning.allowed_weekdays',
        'value' => [1, 2, 3, 4, 5, 6, 7],
        'updated_by' => (int) $user->id,
    ]);
    CompanySetting::query()->create([
        'company_id' => $companyB->id,
        'key' => 'autoplan.weekend_policy',
        'value' => 'require_if_demand',
        'updated_by' => (int) $user->id,
    ]);

    $payload = [
        'month' => '2026-04',
        'employee_ids' => $employeesB->pluck('id')->map(fn ($id): int => (int) $id)->values()->all(),
        'demand' => [
            'weekday' => [
                ['shift_id' => (int) $shiftB->id, 'required_count' => 1],
            ],
            'weekend' => [
                ['shift_id' => (int) $shiftB->id, 'required_count' => 1],
            ],
        ],
        'rules' => [
            'min_rest_hours' => 0,
            'max_consecutive_days' => 31,
            'weekend_fairness' => false,
        ],
    ];

    $response = $this->actingAsUserInCompany($user, $companyB)
        ->postJson(route('scheduling.work_schedules.autoplan.generate'), $payload)
        ->assertCreated();

    $scheduleId = (int) $response->json('data.work_schedule.id');

    $weekendAssignmentsCount = WorkShiftAssignment::query()
        ->where('company_id', $companyB->id)
        ->where('work_schedule_id', $scheduleId)
        ->get()
        ->filter(static fn (WorkShiftAssignment $row): bool => CarbonImmutable::parse((string) $row->date)->isWeekend())
        ->count();

    expect($weekendAssignmentsCount)->toBeGreaterThan(0);

    $crossTenantAssignments = WorkShiftAssignment::query()
        ->where('work_schedule_id', $scheduleId)
        ->where('company_id', $companyC->id)
        ->count();

    expect($crossTenantAssignments)->toBe(0);
});

