<?php

declare(strict_types=1);

use App\Models\LeaveType;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('permission nelkul nem hozhat letre leave type rekordot', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($company);
    $user->syncRoles([]);
    $user->syncPermissions(['companies.view']);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $this->actingAsUserInCompany($user, $company)
        ->postJson(route('admin.leave_types.store'), [
            'code' => 'annual',
            'name' => 'Szabadsag',
            'category' => 'leave',
            'affects_leave_balance' => true,
            'requires_approval' => true,
            'active' => true,
        ])
        ->assertForbidden();
});

it('validalja az egyedi code mezot company scope-ban', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($company);

    LeaveType::query()->create([
        'company_id' => $company->id,
        'code' => 'annual',
        'name' => 'Szabadsag',
        'category' => 'leave',
        'affects_leave_balance' => true,
        'requires_approval' => true,
        'active' => true,
    ]);

    $this->actingAsUserInCompany($user, $company)
        ->postJson(route('admin.leave_types.store'), [
            'code' => 'annual',
            'name' => 'Masik nev',
            'category' => 'leave',
            'affects_leave_balance' => true,
            'requires_approval' => true,
            'active' => true,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code']);
});

it('letrehozza a rekordot a current company scope-ban', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($company);

    $this->actingAsUserInCompany($user, $company)
        ->postJson(route('admin.leave_types.store'), [
            'code' => 'annual',
            'name' => 'Szabadsag',
            'category' => 'leave',
            'affects_leave_balance' => true,
            'requires_approval' => true,
            'active' => true,
        ])
        ->assertCreated()
        ->assertJsonPath('data.company_id', $company->id)
        ->assertJsonPath('data.code', 'annual');

    $this->assertDatabaseHas('leave_types', [
        'company_id' => $company->id,
        'code' => 'annual',
        'category' => 'leave',
    ]);
});
