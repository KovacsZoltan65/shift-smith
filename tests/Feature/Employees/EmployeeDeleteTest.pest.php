<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Employee;
use App\Models\UserEmployee;
use App\Services\EmployeeSupervisorService;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('törli az alkalmazottat company scope-ban', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $tenant->makeCurrent();
    $user = $this->createAdminUser($company);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $employee = Employee::factory()->create(['company_id' => $company->id]);
    $actorEmployeeId = UserEmployee::query()
        ->where('user_id', $user->id)
        ->where('company_id', $company->id)
        ->value('employee_id');

    expect(is_numeric($actorEmployeeId))->toBeTrue();
    app(EmployeeSupervisorService::class)->assignSupervisor(
        (int) $company->id,
        (int) $employee->id,
        (int) $actorEmployeeId,
        '2026-01-01',
        (int) $user->id,
    );

    $this->actingAsUserInCompany($user, $company)
        ->deleteJson(route('employees.destroy', $employee->id), [
            'company_id' => $company->id,
            'effective_from' => '2026-03-06',
            'strategy' => 'none',
        ])
        ->assertOk();

    $this->assertSoftDeleted('employees', ['id' => $employee->id]);
});
