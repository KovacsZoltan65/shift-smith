<?php

declare(strict_types=1);

use App\Models\Employee;
use App\Models\EmployeeAbsence;
use App\Models\LeaveType;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('datumtartomanyra visszaadja a tavollet eventeket', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($company);
    $employee = Employee::factory()->create([
        'company_id' => $company->id,
        'first_name' => 'Bela',
        'last_name' => 'Kovacs',
    ]);
    $leaveType = LeaveType::factory()->create([
        'company_id' => $company->id,
        'name' => 'Betegszabadsag',
        'category' => 'sick_leave',
        'affects_leave_balance' => false,
    ]);

    EmployeeAbsence::query()->create([
        'company_id' => $company->id,
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'date_from' => '2026-03-10',
        'date_to' => '2026-03-11',
        'minutes_per_day' => 480,
        'total_minutes' => 960,
        'note' => null,
        'status' => 'approved',
        'created_by' => $user->id,
    ]);

    $this->actingAsUserInCompany($user, $company)
        ->getJson(route('admin.absences.fetch', [
            'date_from' => '2026-03-01',
            'date_to' => '2026-03-31',
            'employee_ids' => [$employee->id],
        ]))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.extendedProps.entity_type', 'absence')
        ->assertJsonPath('data.0.extendedProps.category', 'sick_leave');
});
