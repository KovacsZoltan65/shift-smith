<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Employee;
use App\Services\Cache\CacheVersionService;
use Illuminate\Support\Facades\Cache;
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

    $versioner = app(CacheVersionService::class);

    Cache::forever('v:employees.fetch', 1);
    Cache::forever('v:selectors.employees', 1);
    Cache::forever('v:selectors.companies', 1);

    $payload = Employee::factory()->make([
        'company_id' => $company->id,
        'first_name' => 'Nagy',
        'last_name'  => 'Anna',
        'email'      => 'nagy.anna@test.hu',
        'active'     => true,
    ])->only(['company_id', 'first_name', 'last_name', 'email', 'phone', 'position', 'hired_at', 'active']);

    $this->actingAs($user)
        ->postJson(route('employees.store'), $payload)
        ->assertOk();

    $this->assertDatabaseHas('employees', [
        'company_id' => $company->id,
        'first_name' => 'Nagy',
        'last_name'  => 'Anna',
        'email'      => 'nagy.anna@test.hu',
    ]);

    expect($versioner->get('employees.fetch'))->toBe(2);
    expect($versioner->get('selectors.employees'))->toBe(2);
    expect($versioner->get('selectors.companies'))->toBe(2); // store always affects company selector (only_with_employees)
});
