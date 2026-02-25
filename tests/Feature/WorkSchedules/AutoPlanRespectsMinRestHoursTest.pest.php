<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Employee;
use App\Models\TenantGroup;
use App\Models\WorkSchedule;
use App\Models\WorkShift;
use App\Models\WorkShiftAssignment;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('autoplan tiszteletben tartja a min_rest_hours szabályt', function (): void {
    $tenant = TenantGroup::factory()->create();
    $company = Company::factory()->create(['tenant_group_id' => $tenant->id]);
    $user = $this->createAdminUser($company);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();
    $user->givePermissionTo('work_schedules.autoplan');

    $employee = Employee::factory()->create(['company_id' => $company->id]);

    $nightShift = WorkShift::factory()->create([
        'company_id' => $company->id,
        'start_time' => '22:00:00',
        'end_time' => '23:59:00',
        'active' => true,
    ]);

    $dayShift = WorkShift::factory()->create([
        'company_id' => $company->id,
        'start_time' => '06:00:00',
        'end_time' => '14:00:00',
        'active' => true,
    ]);

    $previousSchedule = WorkSchedule::factory()->create([
        'company_id' => $company->id,
        'date_from' => '2026-03-01',
        'date_to' => '2026-03-31',
        'status' => 'draft',
    ]);

    WorkShiftAssignment::factory()->create([
        'company_id' => $company->id,
        'work_schedule_id' => $previousSchedule->id,
        'employee_id' => $employee->id,
        'work_shift_id' => $nightShift->id,
        'date' => '2026-03-31',
    ]);

    $payload = [
        'month' => '2026-04',
        'employee_ids' => [$employee->id],
        'demand' => [
            'weekday' => [
                ['shift_id' => $dayShift->id, 'required_count' => 1],
            ],
            'weekend' => [
                ['shift_id' => $dayShift->id, 'required_count' => 1],
            ],
        ],
        'rules' => [
            'min_rest_hours' => 8,
            'max_consecutive_days' => 31,
            'weekend_fairness' => false,
        ],
    ];

    $response = $this->actingAsUserInCompany($user, $company)
        ->postJson(route('scheduling.work_schedules.autoplan.generate'), $payload)
        ->assertCreated();

    $scheduleId = (int) $response->json('data.work_schedule.id');
    $missing = $response->json('data.missing');

    $this->assertDatabaseMissing('work_shift_assignments', [
        'company_id' => $company->id,
        'work_schedule_id' => $scheduleId,
        'employee_id' => $employee->id,
        'date' => '2026-04-01',
    ]);

    expect(collect($missing)->contains(function (array $row): bool {
        return (string) ($row['date'] ?? '') === '2026-04-01';
    }))->toBeTrue();
});
