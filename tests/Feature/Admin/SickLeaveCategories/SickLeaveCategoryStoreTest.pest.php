<?php

declare(strict_types=1);

use App\Models\SickLeaveCategory;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('permission nelkul nem hozhat letre sick leave category rekordot', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($company);
    $user->syncRoles([]);
    $user->syncPermissions(['companies.view']);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $this->actingAsUserInCompany($user, $company)
        ->postJson(route('admin.sick_leave_categories.store'), [
            'name' => 'Sajat betegseg',
            'description' => 'Teszt',
            'active' => true,
            'order_index' => 10,
        ])
        ->assertForbidden();
});

it('code nelkul is letrehozza a rekordot es general egy company-scope egyedi kodot', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($company);

    $response = $this->actingAsUserInCompany($user, $company)
        ->postJson(route('admin.sick_leave_categories.store'), [
            'name' => 'Sajat betegseg',
            'description' => 'Altalanos betegszabadsag',
            'active' => true,
            'order_index' => 2,
        ])
        ->assertCreated()
        ->assertJsonPath('data.company_id', $company->id)
        ->assertJsonPath('data.code', 'sajat_betegseg');

    $this->assertDatabaseHas('sick_leave_categories', [
        'company_id' => $company->id,
        'code' => 'sajat_betegseg',
        'name' => 'Sajat betegseg',
    ]);

    expect($response->json('data.description'))->toBe('Altalanos betegszabadsag');
});

it('ugyanazzal a nevvel ket create kulonbozo suffixelt kodot general', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($company);

    $first = $this->actingAsUserInCompany($user, $company)
        ->postJson(route('admin.sick_leave_categories.store'), [
            'name' => 'Gyermek apolasa',
            'description' => null,
            'active' => true,
            'order_index' => 1,
        ])
        ->assertCreated();

    $second = $this->actingAsUserInCompany($user, $company)
        ->postJson(route('admin.sick_leave_categories.store'), [
            'name' => 'Gyermek apolasa',
            'description' => null,
            'active' => true,
            'order_index' => 2,
        ])
        ->assertCreated();

    expect($first->json('data.code'))->toBe('gyermek_apolasa');
    expect($second->json('data.code'))->toBe('gyermek_apolasa_2');
});

it('azonos nev masik companyban is ugyanazt az alap kodot kaphatja', function (): void {
    [$tenant, $companyA] = $this->createTenantWithCompany();
    [, $companyB] = $this->createTenantWithCompany([], ['tenant_group_id' => $tenant->id]);
    $userA = $this->createAdminUser($companyA);
    $userB = $this->createAdminUser($companyB);

    $this->actingAsUserInCompany($userA, $companyA)
        ->postJson(route('admin.sick_leave_categories.store'), [
            'name' => 'Baleset',
            'description' => null,
            'active' => true,
            'order_index' => 0,
        ])
        ->assertCreated()
        ->assertJsonPath('data.code', 'baleset');

    $this->actingAsUserInCompany($userB, $companyB)
        ->postJson(route('admin.sick_leave_categories.store'), [
            'name' => 'Baleset',
            'description' => null,
            'active' => true,
            'order_index' => 0,
        ])
        ->assertCreated()
        ->assertJsonPath('data.code', 'baleset');
});
