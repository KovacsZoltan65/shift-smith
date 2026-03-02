<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\CompanyEmployee;
use App\Models\Employee;
use App\Models\TenantGroup;
use App\Models\User;
use App\Models\UserEmployee;
use Database\Seeders\Pivot\UserEmployeeSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
    TenantGroup::forgetCurrent();
});

afterEach(function (): void {
    TenantGroup::forgetCurrent();
});

it('creates user_employee mapping by email match within tenant', function (): void {
    $tenant = TenantGroup::factory()->create();
    $company = Company::factory()->create([
        'tenant_group_id' => $tenant->id,
        'active' => true,
    ]);

    $user = User::factory()->create(['email' => 'match@example.com']);
    $user->assignRole('user');
    $user->companies()->syncWithoutDetaching([(int) $company->id]);

    $employee = Employee::factory()->create([
        'company_id' => $company->id,
        'email' => 'match@example.com',
    ]);
    CompanyEmployee::query()->updateOrCreate(
        ['company_id' => $company->id, 'employee_id' => $employee->id],
        ['active' => true]
    );

    app(UserEmployeeSeeder::class)->run();

    $pivot = UserEmployee::query()
        ->where('user_id', (int) $user->id)
        ->where('employee_id', (int) $employee->id)
        ->first();

    expect($pivot)->not->toBeNull();
});

it('creates fallback mapping to first employee in first accessible company when no email match', function (): void {
    $tenant = TenantGroup::factory()->create();
    $company = Company::factory()->create([
        'tenant_group_id' => $tenant->id,
        'active' => true,
    ]);

    $user = User::factory()->create(['email' => 'no-match@example.com']);
    $user->assignRole('user');
    $user->companies()->syncWithoutDetaching([(int) $company->id]);

    $employeeB = Employee::factory()->create([
        'company_id' => $company->id,
        'email' => 'b@example.com',
    ]);
    $employeeA = Employee::factory()->create([
        'company_id' => $company->id,
        'email' => 'a@example.com',
    ]);

    CompanyEmployee::query()->updateOrCreate(
        ['company_id' => $company->id, 'employee_id' => $employeeA->id],
        ['active' => true]
    );
    CompanyEmployee::query()->updateOrCreate(
        ['company_id' => $company->id, 'employee_id' => $employeeB->id],
        ['active' => true]
    );

    $expectedEmployeeId = min((int) $employeeA->id, (int) $employeeB->id);

    app(UserEmployeeSeeder::class)->run();

    $mappedEmployeeId = UserEmployee::query()
        ->where('user_id', (int) $user->id)
        ->orderBy('employee_id')
        ->value('employee_id');

    expect((int) $mappedEmployeeId)->toBe($expectedEmployeeId);
});

it('does not create cross-tenant mapping when same email exists in another tenant', function (): void {
    $tenantOne = TenantGroup::factory()->create();
    $tenantTwo = TenantGroup::factory()->create();

    $companyOne = Company::factory()->create([
        'tenant_group_id' => $tenantOne->id,
        'active' => true,
    ]);
    $companyTwo = Company::factory()->create([
        'tenant_group_id' => $tenantTwo->id,
        'active' => true,
    ]);

    $user = User::factory()->create(['email' => 'shared@example.com']);
    $user->assignRole('user');
    $user->companies()->syncWithoutDetaching([(int) $companyOne->id]);

    $tenantOneEmployee = Employee::factory()->create([
        'company_id' => $companyOne->id,
        'email' => 'other@example.com',
    ]);
    CompanyEmployee::query()->updateOrCreate(
        ['company_id' => $companyOne->id, 'employee_id' => $tenantOneEmployee->id],
        ['active' => true]
    );

    $tenantTwoEmployee = Employee::factory()->create([
        'company_id' => $companyTwo->id,
        'email' => 'shared@example.com',
    ]);
    CompanyEmployee::query()->updateOrCreate(
        ['company_id' => $companyTwo->id, 'employee_id' => $tenantTwoEmployee->id],
        ['active' => true]
    );

    app(UserEmployeeSeeder::class)->run();

    $mappedEmployeeIds = UserEmployee::query()
        ->where('user_id', (int) $user->id)
        ->pluck('employee_id')
        ->map(static fn ($id): int => (int) $id)
        ->values()
        ->all();

    expect($mappedEmployeeIds)->toContain((int) $tenantOneEmployee->id);
    expect($mappedEmployeeIds)->not->toContain((int) $tenantTwoEmployee->id);
});

it('never produces duplicate company-employee assignments after seeding', function (): void {
    $tenant = TenantGroup::factory()->create();
    $company = Company::factory()->create([
        'tenant_group_id' => $tenant->id,
        'active' => true,
    ]);

    $users = User::factory()->count(3)->create();
    foreach ($users as $user) {
        $user->assignRole('user');
        $user->companies()->syncWithoutDetaching([(int) $company->id]);
    }

    $employees = Employee::factory()->count(2)->create([
        'company_id' => $company->id,
        'active' => true,
    ]);

    foreach ($employees as $employee) {
        CompanyEmployee::query()->updateOrCreate(
            ['company_id' => $company->id, 'employee_id' => $employee->id],
            ['active' => true]
        );
    }

    app(UserEmployeeSeeder::class)->run();

    $duplicates = DB::table('user_employee')
        ->select('company_id', 'employee_id')
        ->groupBy('company_id', 'employee_id')
        ->havingRaw('COUNT(*) > 1')
        ->count();

    expect($duplicates)->toBe(0);
});
