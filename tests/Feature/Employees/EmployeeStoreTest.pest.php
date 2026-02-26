<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Employee;
use App\Models\Position;
use App\Services\Cache\CacheVersionService;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('validates required fields on employee store (email required)', function (): void {
    $user = $this->createAdminUser();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $company = Company::factory()->create();
    $this->actingAs($user)
        ->postJson(route('employees.store'), [
            'company_id' => $company->id,
            'first_name' => 'Teszt',
            'last_name'  => 'Elek',
            // 'email' => missing
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

it('allows admin to store employee and bumps cache versions', function (): void {
    $user = $this->createAdminUser();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $company = Company::factory()->create();
    $position = Position::factory()->create([
        'company_id' => $company->id,
        'name' => 'Operátor',
    ]);

    $versioner = app(CacheVersionService::class);
    $employeesFetchBefore = $versioner->get('employees.fetch');
    $employeesSelectorBefore = $versioner->get('selectors.employees');
    $companiesSelectorBefore = $versioner->get('selectors.companies');

    $payload = Employee::factory()->make([
        'company_id' => $company->id,
        'first_name' => 'Nagy',
        'last_name'  => 'Anna',
        'email'      => 'nagy.anna@test.hu',
        'address'    => 'Teszt utca 1.',
        'position_id'=> $position->id,
        'hired_at'   => '2026-02-01',
        'active'     => true,
    ])->only(['company_id', 'first_name', 'last_name', 'email', 'phone', 'position_id', 'hired_at', 'active']);

    $payload['address'] = 'Teszt utca 1.';
    $payload['hired_at'] = '2026-02-01';

    $this->actingAs($user)
        ->postJson(route('employees.store'), $payload)
        ->assertCreated();

    $this->assertDatabaseHas('employees', [
        'company_id' => $company->id,
        'first_name' => 'Nagy',
        'last_name'  => 'Anna',
        'email'      => 'nagy.anna@test.hu',
        'address'    => 'Teszt utca 1.',
        'position_id'=> $position->id,
    ]);

    expect($versioner->get('employees.fetch'))->toBeGreaterThan($employeesFetchBefore);
    expect($versioner->get('selectors.employees'))->toBeGreaterThan($employeesSelectorBefore);
    expect($versioner->get('selectors.companies'))->toBeGreaterThan($companiesSelectorBefore); // store always affects company selector (only_with_employees)
});
