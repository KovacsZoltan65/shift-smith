<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Employee;
use App\Models\TenantGroup;
use App\Models\WorkShift;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('autoplan elutasítja a cégidegen employee_ids payloadot', function (): void {
    $tenantA = TenantGroup::factory()->create();
    $tenantB = TenantGroup::factory()->create();
    $companyA = Company::factory()->create(['tenant_group_id' => $tenantA->id]);
    $companyB = Company::factory()->create(['tenant_group_id' => $tenantB->id]);
    $user = $this->createAdminUser($companyA);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();
    $user->givePermissionTo('work_schedules.autoplan');

    $employeeA = Employee::factory()->create(['company_id' => $companyA->id]);
    $employeeB = Employee::factory()->create(['company_id' => $companyB->id]);

    $shiftA = WorkShift::factory()->create([
        'company_id' => $companyA->id,
        'start_time' => '08:00:00',
        'end_time' => '16:00:00',
        'active' => true,
    ]);

    $payload = [
        'month' => '2026-04',
        'employee_ids' => [$employeeA->id, $employeeB->id],
        'demand' => [
            'weekday' => [
                ['shift_id' => $shiftA->id, 'required_count' => 1],
            ],
            'weekend' => [
                ['shift_id' => $shiftA->id, 'required_count' => 1],
            ],
        ],
        'rules' => [
            'min_rest_hours' => 8,
            'max_consecutive_days' => 31,
            'weekend_fairness' => true,
        ],
    ];

    $this->actingAsUserInCompany($user, $companyA)
        ->postJson(route('scheduling.work_schedules.autoplan.generate'), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['employee_ids']);
});
