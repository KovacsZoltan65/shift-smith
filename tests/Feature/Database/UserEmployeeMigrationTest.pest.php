<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Employee;
use App\Models\TenantGroup;
use App\Models\User;
use App\Models\UserEmployee;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

it('adds company_id to user_employee and enforces unique user-company assignments', function (): void {
    expect(Schema::hasColumn('user_employee', 'company_id'))->toBeTrue();

    $tenant = TenantGroup::factory()->create();
    $company = Company::factory()->create([
        'tenant_group_id' => (int) $tenant->id,
        'active' => true,
    ]);

    $user = User::factory()->create();
    $employeeOne = Employee::factory()->create([
        'company_id' => (int) $company->id,
        'active' => true,
    ]);
    $employeeTwo = Employee::factory()->create([
        'company_id' => (int) $company->id,
        'active' => true,
    ]);

    UserEmployee::query()->create([
        'user_id' => (int) $user->id,
        'company_id' => (int) $company->id,
        'employee_id' => (int) $employeeOne->id,
        'active' => true,
    ]);

    expect(fn () => UserEmployee::query()->create([
        'user_id' => (int) $user->id,
        'company_id' => (int) $company->id,
        'employee_id' => (int) $employeeTwo->id,
        'active' => true,
    ]))->toThrow(QueryException::class);
});
