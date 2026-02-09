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

it('requires email on update too', function (): void {
    $user = $this->createAdminUser();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $company = Company::factory()->create();

    $employee = Employee::factory()->create([
        'company_id' => $company->id,
        'email' => 'old@test.hu',
    ]);

    $this->actingAs($user)
        ->putJson(route('employees.update', $employee->id), [
            'company_id' => $company->id,
            'first_name' => $employee->first_name,
            'last_name'  => $employee->last_name,
            'email'      => '', // invalid
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

it('updates employee and bumps caches; company selector bumps only when company_id changes', function (): void {
    $user = $this->createAdminUser();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $c1 = Company::factory()->create();
    $c2 = Company::factory()->create();

    $employee = Employee::factory()->create([
        'company_id' => $c1->id,
        'email' => 'emp@test.hu',
    ]);

    $versioner = app(CacheVersionService::class);

    Cache::forever('v:employees.fetch', 1);
    Cache::forever('v:selectors.employees', 1);
    Cache::forever('v:selectors.companies', 1);

    // update without company change -> companies selector should remain
    $this->actingAs($user)
        ->putJson(route('employees.update', $employee->id), [
            'company_id' => $c1->id,
            'first_name' => 'Updated',
            'last_name'  => $employee->last_name,
            'email'      => 'emp@test.hu',
            'active'     => true,
        ])
        ->assertOk();

    expect($versioner->get('employees.fetch'))->toBe(2);
    expect($versioner->get('selectors.employees'))->toBe(2);
    expect($versioner->get('selectors.companies'))->toBe(1); // only bumps if company changed

    // now change company -> should bump companies selector
    $this->actingAs($user)
        ->putJson(route('employees.update', $employee->id), [
            'company_id' => $c2->id,
            'first_name' => 'Updated',
            'last_name'  => $employee->last_name,
            'email'      => 'emp@test.hu',
            'active'     => true,
        ])
        ->assertOk();

    expect($versioner->get('selectors.companies'))->toBe(2);
});
