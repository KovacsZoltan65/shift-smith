<?php

declare(strict_types=1);

use App\Models\Employee;
use App\Models\LeaveType;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('letrehoz tavolletet sajat company employee es leave type mellett', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($company);
    $employee = Employee::factory()->create(['company_id' => $company->id]);
    $leaveType = LeaveType::factory()->create([
        'company_id' => $company->id,
        'category' => 'leave',
    ]);

    $this->actingAsUserInCompany($user, $company)
        ->postJson(route('admin.absences.store'), [
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'date_from' => '2026-03-10',
            'date_to' => '2026-03-12',
            'note' => 'Tavaszi szabadsag',
        ])
        ->assertCreated()
        ->assertJsonPath('data.employee_id', $employee->id)
        ->assertJsonPath('data.leave_type_id', $leaveType->id)
        ->assertJsonPath('data.total_minutes', 1440);
});

it('masik company employee-jere nem enged tavolletet rogzitni', function (): void {
    [$tenantA, $companyA] = $this->createTenantWithCompany();
    [$tenantB, $companyB] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($companyA);
    $employee = Employee::factory()->create(['company_id' => $companyB->id]);
    $leaveType = LeaveType::factory()->create([
        'company_id' => $companyA->id,
        'category' => 'leave',
    ]);

    $this->actingAsUserInCompany($user, $companyA)
        ->postJson(route('admin.absences.store'), [
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'date_from' => '2026-03-10',
            'date_to' => '2026-03-10',
        ])
        ->assertNotFound();
});
