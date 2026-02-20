<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeWorkPattern;
use App\Models\WorkPattern;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('átirányítja a vendéget a munkarend dolgozólista végpontról', function (): void {
    $pattern = WorkPattern::factory()->create();

    $this->get(route('work_patterns.employees', ['id' => $pattern->id, 'company_id' => $pattern->company_id]))
        ->assertRedirect();
});

it('megtagadja a munkarend dolgozólista lekérését jogosultság nélkül', function (): void {
    $user = $this->createAdminUser();
    $user->syncRoles([]);
    $user->syncPermissions([]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $pattern = WorkPattern::factory()->create();

    $this->actingAs($user)
        ->getJson(route('work_patterns.employees', ['id' => $pattern->id, 'company_id' => $pattern->company_id]))
        ->assertForbidden();
});

it('visszaadja a kiválasztott munkarendhez tartozó dolgozókat, soft delete szűréssel', function (): void {
    $user = $this->createAdminUser();

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $company = Company::factory()->create();
    $pattern = WorkPattern::factory()->create(['company_id' => $company->id]);
    $otherPattern = WorkPattern::factory()->create(['company_id' => $company->id]);

    $employeeA = Employee::factory()->create(['company_id' => $company->id, 'first_name' => 'Anna', 'last_name' => 'Alpha']);
    $employeeB = Employee::factory()->create(['company_id' => $company->id, 'first_name' => 'Bela', 'last_name' => 'Beta']);
    $employeeOther = Employee::factory()->create(['company_id' => $company->id]);

    EmployeeWorkPattern::factory()->create([
        'company_id' => $company->id,
        'employee_id' => $employeeA->id,
        'work_pattern_id' => $pattern->id,
        'date_from' => '2026-01-01',
        'date_to' => null,
    ]);

    $softDeleted = EmployeeWorkPattern::factory()->create([
        'company_id' => $company->id,
        'employee_id' => $employeeB->id,
        'work_pattern_id' => $pattern->id,
        'date_from' => '2026-02-01',
        'date_to' => null,
    ]);
    $softDeleted->delete();

    EmployeeWorkPattern::factory()->create([
        'company_id' => $company->id,
        'employee_id' => $employeeOther->id,
        'work_pattern_id' => $otherPattern->id,
        'date_from' => '2026-01-01',
        'date_to' => null,
    ]);

    $resp = $this->actingAs($user)
        ->getJson(route('work_patterns.employees', ['id' => $pattern->id, 'company_id' => $company->id]));

    $resp->assertOk()->assertJsonStructure([
        'message',
        'data' => [[
            'id',
            'employee_id',
            'name',
            'email',
            'phone',
            'date_from',
            'date_to',
        ]],
    ]);

    $data = $resp->json('data');
    expect($data)->toHaveCount(1);
    expect($data[0]['employee_id'])->toBe($employeeA->id);
    expect($data[0]['name'])->toContain('Alpha');
});
