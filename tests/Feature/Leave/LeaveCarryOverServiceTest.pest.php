<?php

declare(strict_types=1);

use App\Facades\Settings;
use App\Models\AppSetting;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeLeaveBalance;
use App\Services\Cache\CacheVersionService;
use App\Services\LeaveCarryOverService;
use App\Interfaces\LeaveBalanceRepositoryInterface;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

function seedLeaveMinuteSettings(): void
{
    AppSetting::query()->updateOrCreate(
        ['key' => 'leave.minutes_per_day'],
        [
            'value' => 480,
            'type' => 'int',
            'group' => 'leave',
            'label' => 'Minutes per workday',
            'description' => '8h = 480 minutes',
        ]
    );
}

function putCurrentLeaveCompanyContext(Company $company): void
{
    session()->put([
        'current_company_id' => (int) $company->id,
        'current_tenant_group_id' => (int) $company->tenant_group_id,
    ]);
}

function createLeaveBalance(Employee $employee, Company $company, array $attributes = []): EmployeeLeaveBalance
{
    return EmployeeLeaveBalance::query()->create([
        'employee_id' => (int) $employee->id,
        'company_id' => (int) $company->id,
        'year' => 2025,
        'leave_type' => 'annual_regular',
        'employment_start_date' => $employee->hired_at?->toDateString(),
        'total_minutes' => 9600,
        'used_minutes' => 9120,
        'remaining_minutes' => 480,
        'carried_over_minutes' => 0,
        'carryover_valid_until' => null,
        'rule_applied' => null,
        'has_employer_exception' => false,
        'employee_blocked_periods' => [],
        'agreement_age_bonus_transfer' => false,
        ...$attributes,
    ]);
}

it('applies default rule with no carry over', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($company);
    $employee = Employee::query()->where('company_id', $company->id)->latest('id')->firstOrFail();

    seedLeaveMinuteSettings();
    createLeaveBalance($employee, $company);

    $this->actingAsUserInCompany($user, $company);
    putCurrentLeaveCompanyContext($company);

    $result = app(LeaveCarryOverService::class)->calculateForEmployeeYear((int) $employee->id, 2025);

    expect(Settings::getInt('leave.minutes_per_day'))->toBe(480)
        ->and($result['annual_regular']->transferable_minutes)->toBe(0)
        ->and($result['annual_regular']->must_expire_minutes)->toBe(480)
        ->and($result['annual_regular']->valid_until)->toBeNull()
        ->and($result['annual_regular']->rule_applied)->toBe('default_no_carry_over');
});

it('applies october entry rule until march 31', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($company);
    $employee = Employee::query()->where('company_id', $company->id)->latest('id')->firstOrFail();

    seedLeaveMinuteSettings();
    createLeaveBalance($employee, $company, [
        'employment_start_date' => '2025-10-15',
    ]);

    $this->actingAsUserInCompany($user, $company);
    putCurrentLeaveCompanyContext($company);

    $result = app(LeaveCarryOverService::class)->calculateForEmployeeYear((int) $employee->id, 2025);

    expect($result['annual_regular']->transferable_minutes)->toBe(480)
        ->and($result['annual_regular']->must_expire_minutes)->toBe(0)
        ->and($result['annual_regular']->valid_until)->toBe('2026-03-31')
        ->and($result['annual_regular']->rule_applied)->toBe('october_entry');
});

it('calculates age bonus quarter in minutes', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($company);
    $employee = Employee::query()->where('company_id', $company->id)->latest('id')->firstOrFail();

    seedLeaveMinuteSettings();
    createLeaveBalance($employee, $company, [
        'leave_type' => 'annual_age_bonus',
        'remaining_minutes' => 480,
        'agreement_age_bonus_transfer' => true,
    ]);

    $this->actingAsUserInCompany($user, $company);
    putCurrentLeaveCompanyContext($company);

    $result = app(LeaveCarryOverService::class)->calculateForEmployeeYear((int) $employee->id, 2025);

    expect($result['annual_age_bonus']->transferable_minutes)->toBe(120)
        ->and($result['annual_age_bonus']->must_expire_minutes)->toBe(360)
        ->and($result['annual_age_bonus']->valid_until)->toBe('2026-03-31')
        ->and($result['annual_age_bonus']->rule_applied)->toBe('age_bonus_quarter');
});

it('applies employee blocked rule with 60 day validity', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($company);
    $employee = Employee::query()->where('company_id', $company->id)->latest('id')->firstOrFail();

    seedLeaveMinuteSettings();
    createLeaveBalance($employee, $company, [
        'employee_blocked_periods' => [
            ['start_date' => '2025-12-20', 'end_date' => '2026-01-10'],
        ],
    ]);

    $this->actingAsUserInCompany($user, $company);
    putCurrentLeaveCompanyContext($company);

    $result = app(LeaveCarryOverService::class)->calculateForEmployeeYear((int) $employee->id, 2025);

    expect($result['annual_regular']->transferable_minutes)->toBe(480)
        ->and($result['annual_regular']->must_expire_minutes)->toBe(0)
        ->and($result['annual_regular']->valid_until)->toBe('2026-03-11')
        ->and($result['annual_regular']->rule_applied)->toBe('employee_blocked');
});

it('applies employer exception rule with 60 day validity', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($company);
    $employee = Employee::query()->where('company_id', $company->id)->latest('id')->firstOrFail();

    seedLeaveMinuteSettings();
    createLeaveBalance($employee, $company, [
        'has_employer_exception' => true,
    ]);

    $this->actingAsUserInCompany($user, $company);
    putCurrentLeaveCompanyContext($company);

    $result = app(LeaveCarryOverService::class)->calculateForEmployeeYear((int) $employee->id, 2025);

    expect($result['annual_regular']->transferable_minutes)->toBe(480)
        ->and($result['annual_regular']->must_expire_minutes)->toBe(0)
        ->and($result['annual_regular']->valid_until)->toBe('2026-03-01')
        ->and($result['annual_regular']->rule_applied)->toBe('employer_exception');
});

it('enforces company scope inside the same tenant', function (): void {
    $tenant = \App\Models\TenantGroup::factory()->create();
    $companyA = Company::factory()->create(['tenant_group_id' => $tenant->id]);
    $companyB = Company::factory()->create(['tenant_group_id' => $tenant->id]);
    $user = $this->createAdminUser($companyA);
    $employeeB = Employee::factory()->create(['company_id' => $companyB->id]);

    seedLeaveMinuteSettings();
    createLeaveBalance($employeeB, $companyB);

    $this->actingAsUserInCompany($user, $companyA);
    putCurrentLeaveCompanyContext($companyA);
    $tenant->makeCurrent();

    expect(fn () => app(LeaveCarryOverService::class)->calculateForEmployeeYear((int) $employeeB->id, 2025))
        ->toThrow(\DomainException::class);
});

it('keeps tenant isolation across tenant groups', function (): void {
    [$tenantA, $companyA] = $this->createTenantWithCompany();
    [$tenantB, $companyB] = $this->createTenantWithCompany();
    $userA = $this->createAdminUser($companyA);
    $employeeB = Employee::factory()->create(['company_id' => $companyB->id]);

    seedLeaveMinuteSettings();
    createLeaveBalance($employeeB, $companyB, [
        'remaining_minutes' => 960,
    ]);

    $this->actingAsUserInCompany($userA, $companyA);
    putCurrentLeaveCompanyContext($companyA);
    $tenantA->makeCurrent();

    expect(fn () => app(LeaveCarryOverService::class)->calculateForEmployeeYear((int) $employeeB->id, 2025))
        ->toThrow(\DomainException::class);
});

it('invalidates cached result after leave balance mutation', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($company);
    $employee = Employee::query()->where('company_id', $company->id)->latest('id')->firstOrFail();

    seedLeaveMinuteSettings();
    createLeaveBalance($employee, $company, [
        'employment_start_date' => '2025-10-15',
    ]);

    $this->actingAsUserInCompany($user, $company);
    putCurrentLeaveCompanyContext($company);

    $service = app(LeaveCarryOverService::class);
    $repository = app(LeaveBalanceRepositoryInterface::class);
    $versioner = app(CacheVersionService::class);

    expect($service->calculateForEmployeeYear((int) $employee->id, 2025)['annual_regular']->transferable_minutes)->toBe(480);

    $beforeVersion = $versioner->get("leave_carryover:{$company->id}:{$employee->id}:2025");

    $repository->updateRemainingMinutes((int) $company->id, (int) $employee->id, 2025, 'annual_regular', 0);

    expect($versioner->get("leave_carryover:{$company->id}:{$employee->id}:2025"))->toBeGreaterThan($beforeVersion)
        ->and($service->calculateForEmployeeYear((int) $employee->id, 2025)['annual_regular']->transferable_minutes)->toBe(0)
        ->and($service->calculateForEmployeeYear((int) $employee->id, 2025)['annual_regular']->rule_applied)->toBe('default_no_carry_over');
});
