<?php

declare(strict_types=1);

use App\Models\LeaveType;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('frissiti a sajat company leave type rekordjat', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($company);
    $leaveType = LeaveType::factory()->create([
        'company_id' => $company->id,
        'code' => 'annual',
        'name' => 'Szabadsag',
    ]);

    $this->actingAsUserInCompany($user, $company)
        ->putJson(route('admin.leave_types.update', $leaveType->id), [
            'code' => 'annual',
            'name' => 'Frissitett szabadsag',
            'category' => 'paid_absence',
            'affects_leave_balance' => false,
            'requires_approval' => true,
            'active' => false,
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Frissitett szabadsag')
        ->assertJsonPath('data.category', 'paid_absence')
        ->assertJsonPath('data.active', false);

    $this->assertDatabaseHas('leave_types', [
        'id' => $leaveType->id,
        'company_id' => $company->id,
        'name' => 'Frissitett szabadsag',
        'category' => 'paid_absence',
        'active' => false,
    ]);
});

it('nem frissiti masik company rekordjat', function (): void {
    [$tenantA, $companyA] = $this->createTenantWithCompany();
    [$tenantB, $companyB] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($companyA);
    $foreign = LeaveType::factory()->create([
        'company_id' => $companyB->id,
    ]);

    $this->actingAsUserInCompany($user, $companyA)
        ->putJson(route('admin.leave_types.update', $foreign->id), [
            'code' => $foreign->code,
            'name' => 'Tiltott update',
            'category' => $foreign->category,
            'affects_leave_balance' => $foreign->affects_leave_balance,
            'requires_approval' => $foreign->requires_approval,
            'active' => $foreign->active,
        ])
        ->assertNotFound();
});
