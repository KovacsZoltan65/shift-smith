<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeWorkPattern;
use App\Models\WorkPattern;
use App\Services\Cache\CacheVersionService;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('megtagadja a hozzárendelés törlést jogosultság nélkül', function (): void {
    $user = $this->createAdminUser();
    $user->syncRoles([]);
    $user->syncPermissions([]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $company = Company::factory()->create();
    $employee = Employee::factory()->create(['company_id' => $company->id]);
    $pattern = WorkPattern::factory()->create(['company_id' => $company->id]);
    $assignment = EmployeeWorkPattern::factory()->create([
        'company_id' => $company->id,
        'employee_id' => $employee->id,
        'work_pattern_id' => $pattern->id,
    ]);

    $this->actingAs($user)
        ->deleteJson(route('employee_work_patterns.destroy', [
            'employee' => $employee->id,
            'id' => $assignment->id,
        ]))
        ->assertForbidden();
});

it('soft delete-olja a hozzárendelést és bumpolja a cache verziót', function (): void {
    $user = $this->createAdminUser();

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $company = Company::factory()->create();
    $employee = Employee::factory()->create(['company_id' => $company->id]);
    $pattern = WorkPattern::factory()->create(['company_id' => $company->id]);
    $assignment = EmployeeWorkPattern::factory()->create([
        'company_id' => $company->id,
        'employee_id' => $employee->id,
        'work_pattern_id' => $pattern->id,
    ]);

    $versioner = app(CacheVersionService::class);
    Cache::forever("v:employee_work_patterns.list.company_{$company->id}", 1);

    $this->actingAs($user)
        ->deleteJson(route('employee_work_patterns.destroy', [
            'employee' => $employee->id,
            'id' => $assignment->id,
        ]))
        ->assertOk();

    $this->assertSoftDeleted('employee_work_patterns', ['id' => $assignment->id]);
    expect($versioner->get("employee_work_patterns.list.company_{$company->id}"))->toBe(2);
});
