<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Employee;
use App\Models\WorkShift;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('autoplan draft work schedule-t és generation reportot hoz létre', function (): void {
    $user = $this->createAdminUser();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();
    $user->givePermissionTo('work_schedules.autoplan');

    $company = Company::factory()->create();
    $employeeA = Employee::factory()->create(['company_id' => $company->id]);
    $employeeB = Employee::factory()->create(['company_id' => $company->id]);
    $shift = WorkShift::factory()->create([
        'company_id' => $company->id,
        'start_time' => '08:00:00',
        'end_time' => '16:00:00',
        'active' => true,
    ]);

    $payload = [
        'month' => '2026-04',
        'employee_ids' => [$employeeA->id, $employeeB->id],
        'demand' => [
            'weekday' => [
                ['shift_id' => $shift->id, 'required_count' => 1],
            ],
            'weekend' => [
                ['shift_id' => $shift->id, 'required_count' => 1],
            ],
        ],
        'rules' => [
            'min_rest_hours' => 8,
            'max_consecutive_days' => 31,
            'weekend_fairness' => true,
        ],
    ];

    $response = $this->actingAs($user)
        ->withSession(['current_company_id' => $company->id])
        ->postJson(route('scheduling.work_schedules.autoplan.generate'), $payload)
        ->assertCreated()
        ->assertJsonPath('data.work_schedule.status', 'draft');

    $scheduleId = (int) $response->json('data.work_schedule.id');
    $reportId = (int) $response->json('data.generation_report_id');

    $this->assertDatabaseHas('work_schedules', [
        'id' => $scheduleId,
        'company_id' => $company->id,
        'status' => 'draft',
    ]);

    $this->assertDatabaseHas('generation_reports', [
        'id' => $reportId,
        'company_id' => $company->id,
        'work_schedule_id' => $scheduleId,
        'created_by' => $user->id,
    ]);

    $this->assertDatabaseHas('activity_log', [
        'event' => 'autoplan.generate',
        'subject_type' => \App\Models\WorkSchedule::class,
        'subject_id' => $scheduleId,
    ]);
});
