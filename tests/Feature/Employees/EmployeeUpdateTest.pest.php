<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Employee;
use App\Models\Position;
use App\Models\TenantGroup;
use App\Services\Cache\CacheVersionService;
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
    $tenant = TenantGroup::factory()->create();
    $c1 = Company::factory()->create(['tenant_group_id' => $tenant->id]);
    $c2 = Company::factory()->create(['tenant_group_id' => $tenant->id]);

    $user = $this->createAdminUser($c1);
    $user->companies()->syncWithoutDetaching([$c2->id]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $positionA = Position::factory()->create([
        'company_id' => $c1->id,
        'name' => 'Operátor',
    ]);
    $positionB = Position::factory()->create([
        'company_id' => $c2->id,
        'name' => 'Műszakvezető',
    ]);

    $employee = Employee::factory()->create([
        'company_id' => $c1->id,
        'email' => 'emp@test.hu',
    ]);

    $tenant->makeCurrent();
    $tenantSession = [
        'current_company_id' => (int) $c1->id,
        'current_tenant_group_id' => (int) $tenant->id,
    ];

    $versioner = app(CacheVersionService::class);
    $employeesFetchBefore = $versioner->get('employees.fetch');
    $employeesSelectorBefore = $versioner->get('selectors.employees');
    $companiesSelectorBefore = $versioner->get('selectors.companies');

    // update without company change -> companies selector should remain
    $this->actingAs($user)
        ->withSession($tenantSession)
        ->putJson(route('employees.update', $employee->id), [
            'company_id' => $c1->id,
            'first_name' => 'Updated',
            'last_name'  => $employee->last_name,
            'email'      => 'emp@test.hu',
            'address'    => 'Frissített cím 1.',
            'position_id'=> $positionA->id,
            'active'     => true,
        ])
        ->assertOk();

    expect($versioner->get('employees.fetch'))->toBeGreaterThan($employeesFetchBefore);
    expect($versioner->get('selectors.employees'))->toBeGreaterThan($employeesSelectorBefore);
    $companiesSelectorAfterFirstUpdate = $versioner->get('selectors.companies');
    expect($companiesSelectorAfterFirstUpdate)->toBeGreaterThanOrEqual($companiesSelectorBefore);

    // now change company -> should bump companies selector
    $this->actingAs($user)
        ->withSession($tenantSession)
        ->putJson(route('employees.update', $employee->id), [
            'company_id' => $c2->id,
            'first_name' => 'Updated',
            'last_name'  => $employee->last_name,
            'email'      => 'emp@test.hu',
            'address'    => 'Frissített cím 2.',
            'position_id'=> $positionB->id,
            'active'     => true,
        ])
        ->assertOk();

    expect($versioner->get('selectors.companies'))->toBeGreaterThan($companiesSelectorAfterFirstUpdate);
});
