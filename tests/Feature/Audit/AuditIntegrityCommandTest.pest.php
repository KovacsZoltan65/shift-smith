<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\CompanyEmployee;
use App\Models\CompanyUser;
use App\Models\Employee;
use App\Models\TenantGroup;
use App\Models\User;
use App\Models\UserEmployee;
use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('writes a JSON integrity report and detects missing company_employee mappings', function (): void {
    $tenant = TenantGroup::factory()->create();
    $company = Company::factory()->create([
        'tenant_group_id' => $tenant->id,
        'active' => true,
    ]);

    $user = User::factory()->create();
    $employee = Employee::factory()->create([
        'company_id' => $company->id,
        'active' => true,
    ]);

    CompanyEmployee::query()
        ->where('company_id', $company->id)
        ->where('employee_id', $employee->id)
        ->delete();

    CompanyUser::query()->updateOrCreate([
        'company_id' => $company->id,
        'user_id' => $user->id,
    ]);

    UserEmployee::query()->create([
        'user_id' => $user->id,
        'company_id' => $company->id,
        'employee_id' => $employee->id,
        'active' => true,
    ]);

    $exitCode = Artisan::call('audit:integrity', [
        '--tenant' => [(string) $tenant->id],
        '--json' => true,
    ]);

    $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

    expect($exitCode)->toBe(1)
        ->and($payload['summary']['fail'])->toBeGreaterThanOrEqual(1)
        ->and($payload['report_path'])->toBeString()
        ->and(file_exists($payload['report_path']))->toBeTrue()
        ->and(collect($payload['checks'])->contains(
            fn (array $check): bool => $check['id'] === 'B5.tenant_'.$tenant->id && $check['count'] === 1
        ))->toBeTrue();
});

it('reports clean P0 checks as OK when no violations exist for the tenant', function (): void {
    $tenant = TenantGroup::factory()->create();
    $company = Company::factory()->create([
        'tenant_group_id' => $tenant->id,
        'active' => true,
    ]);

    $user = User::factory()->create();
    $employee = Employee::factory()->create([
        'company_id' => $company->id,
        'active' => true,
    ]);

    $user->companies()->syncWithoutDetaching([$company->id]);
    $company->employees()->syncWithoutDetaching([$employee->id => ['active' => true]]);
    UserEmployee::query()->create([
        'user_id' => $user->id,
        'company_id' => $company->id,
        'employee_id' => $employee->id,
        'active' => true,
    ]);

    $exitCode = Artisan::call('audit:integrity', [
        '--tenant' => [(string) $tenant->id],
        '--json' => true,
    ]);

    $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

    expect($exitCode)->toBe(0)
        ->and(collect($payload['checks'])->contains(
            fn (array $check): bool => $check['id'] === 'B5.tenant_'.$tenant->id && $check['count'] === 0
        ))->toBeTrue();
});

it('default console output lists only nonzero checks while all includes pass rows', function (): void {
    $tenant = TenantGroup::factory()->create();
    $company = Company::factory()->create([
        'tenant_group_id' => $tenant->id,
        'active' => true,
    ]);

    $rolelessUser = User::factory()->create();
    $employee = Employee::factory()->create([
        'company_id' => $company->id,
        'active' => true,
    ]);

    CompanyEmployee::query()
        ->where('company_id', $company->id)
        ->where('employee_id', $employee->id)
        ->delete();

    UserEmployee::query()->create([
        'user_id' => $rolelessUser->id,
        'company_id' => $company->id,
        'employee_id' => $employee->id,
        'active' => true,
    ]);

    Artisan::call('audit:integrity', [
        '--tenant' => [(string) $tenant->id],
    ]);
    $defaultOutput = Artisan::output();

    expect($defaultOutput)->toContain("B5.tenant_{$tenant->id}")
        ->and($defaultOutput)->toContain("B6.tenant_{$tenant->id}")
        ->and($defaultOutput)->toContain('D1')
        ->and($defaultOutput)->not->toContain("B4.tenant_{$tenant->id}")
        ->and($defaultOutput)->not->toContain('PASS');

    Artisan::call('audit:integrity', [
        '--tenant' => [(string) $tenant->id],
        '--all' => true,
    ]);
    $allOutput = Artisan::output();

    expect($allOutput)->toContain("B4.tenant_{$tenant->id}")
        ->and($allOutput)->toContain('PASS');
});
