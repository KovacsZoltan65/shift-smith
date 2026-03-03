<?php

declare(strict_types=1);

use App\Models\LeaveCategory;
use App\Models\LeaveType;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();

    $this->seedLeaveCategories = function ($company): void {
        foreach ([
            ['code' => 'leave', 'name' => 'Szabadsag'],
            ['code' => 'sick_leave', 'name' => 'Betegszabadsag'],
            ['code' => 'paid_absence', 'name' => 'Fizetett tavollet'],
            ['code' => 'unpaid_absence', 'name' => 'Fizetes nelkuli tavollet'],
        ] as $index => $item) {
            LeaveCategory::factory()->create([
                'company_id' => $company->id,
                'code' => $item['code'],
                'name' => $item['name'],
                'order_index' => ($index + 1) * 10,
            ]);
        }
    };
});

it('permission nelkul nem hozhat letre leave type rekordot', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($company);
    ($this->seedLeaveCategories)($company);
    $user->syncRoles([]);
    $user->syncPermissions(['companies.view']);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $this->actingAsUserInCompany($user, $company)
        ->postJson(route('admin.leave_types.store'), [
            'name' => 'Szabadsag',
            'category' => 'leave',
            'affects_leave_balance' => true,
            'requires_approval' => true,
            'active' => true,
        ])
        ->assertForbidden();
});

it('code nelkul is letrehozza a rekordot es general egy company-scope egyedi kodot', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($company);
    ($this->seedLeaveCategories)($company);

    $response = $this->actingAsUserInCompany($user, $company)
        ->postJson(route('admin.leave_types.store'), [
            'name' => 'Fizetes nelkuli tavollet',
            'category' => 'unpaid_absence',
            'affects_leave_balance' => false,
            'requires_approval' => true,
            'active' => true,
        ])
        ->assertCreated()
        ->assertJsonPath('data.company_id', $company->id);

    expect($response->json('data.code'))->toBe('lt_fizetes_nelkuli_tavollet');

    $this->assertDatabaseHas('leave_types', [
        'company_id' => $company->id,
        'code' => 'lt_fizetes_nelkuli_tavollet',
        'category' => 'unpaid_absence',
    ]);
});

it('ugyanazzal a nevvel ket create kulonbozo suffixelt kodot general', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($company);
    ($this->seedLeaveCategories)($company);

    $first = $this->actingAsUserInCompany($user, $company)
        ->postJson(route('admin.leave_types.store'), [
            'name' => 'Szabadsag',
            'category' => 'leave',
            'affects_leave_balance' => true,
            'requires_approval' => true,
            'active' => true,
        ])
        ->assertCreated();

    $second = $this->actingAsUserInCompany($user, $company)
        ->postJson(route('admin.leave_types.store'), [
            'name' => 'Szabadsag',
            'category' => 'leave',
            'affects_leave_balance' => true,
            'requires_approval' => true,
            'active' => true,
        ])
        ->assertCreated();

    expect($first->json('data.code'))->toBe('lt_szabadsag');
    expect($second->json('data.code'))->toBe('lt_szabadsag_2');

    $this->assertDatabaseHas('leave_types', [
        'company_id' => $company->id,
        'code' => 'lt_szabadsag',
        'category' => 'leave',
    ]);

    $this->assertDatabaseHas('leave_types', [
        'company_id' => $company->id,
        'code' => 'lt_szabadsag_2',
        'category' => 'leave',
    ]);
});

it('azonos nev masik companyban is ugyanazt az alap kodot kaphatja', function (): void {
    [$tenant, $companyA] = $this->createTenantWithCompany();
    [, $companyB] = $this->createTenantWithCompany([], ['tenant_group_id' => $tenant->id]);
    $user = $this->createAdminUser($companyA);
    ($this->seedLeaveCategories)($companyA);

    $this->actingAsUserInCompany($user, $companyA)
        ->postJson(route('admin.leave_types.store'), [
            'name' => 'Szabadsag',
            'category' => 'leave',
            'affects_leave_balance' => true,
            'requires_approval' => true,
            'active' => true,
        ])
        ->assertCreated()
        ->assertJsonPath('data.code', 'lt_szabadsag');

    $otherUser = $this->createAdminUser($companyB);
    ($this->seedLeaveCategories)($companyB);

    $this->actingAsUserInCompany($otherUser, $companyB)
        ->postJson(route('admin.leave_types.store'), [
            'name' => 'Szabadsag',
            'category' => 'leave',
            'affects_leave_balance' => true,
            'requires_approval' => true,
            'active' => true,
        ])
        ->assertCreated()
        ->assertJsonPath('data.company_id', $companyB->id)
        ->assertJsonPath('data.code', 'lt_szabadsag');
});
