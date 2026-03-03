<?php

declare(strict_types=1);

use App\Models\LeaveType;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('422 validation hibat ad ha update soran modositani akarjak a kodot', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($company);
    $leaveType = LeaveType::factory()->create([
        'company_id' => $company->id,
        'code' => 'annual_leave',
        'name' => 'Szabadsag',
    ]);

    $this->actingAsUserInCompany($user, $company)
        ->putJson(route('admin.leave_types.update', $leaveType->id), [
            'code' => 'annual_leave_2',
            'name' => 'Szabadsag',
            'category' => 'leave',
            'affects_leave_balance' => true,
            'requires_approval' => true,
            'active' => true,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code']);

    $this->assertDatabaseHas('leave_types', [
        'id' => $leaveType->id,
        'company_id' => $company->id,
        'code' => 'annual_leave',
        'name' => 'Szabadsag',
    ]);
});

it('sikeresen frissiti a nevet valtozatlan kod mellett', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($company);
    $leaveType = LeaveType::factory()->create([
        'company_id' => $company->id,
        'code' => 'annual_leave',
        'name' => 'Szabadsag',
        'active' => true,
    ]);

    $this->actingAsUserInCompany($user, $company)
        ->putJson(route('admin.leave_types.update', $leaveType->id), [
            'code' => 'annual_leave',
            'name' => 'Eves szabadsag',
            'category' => $leaveType->category,
            'affects_leave_balance' => $leaveType->affects_leave_balance,
            'requires_approval' => $leaveType->requires_approval,
            'active' => true,
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Eves szabadsag');

    $this->assertDatabaseHas('leave_types', [
        'id' => $leaveType->id,
        'name' => 'Eves szabadsag',
        'code' => 'annual_leave',
    ]);
});

it('sikeresen frissiti az active flaget valtozatlan kod mellett', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($company);
    $leaveType = LeaveType::factory()->create([
        'company_id' => $company->id,
        'code' => 'annual_leave',
        'active' => true,
    ]);

    $this->actingAsUserInCompany($user, $company)
        ->putJson(route('admin.leave_types.update', $leaveType->id), [
            'code' => 'annual_leave',
            'name' => $leaveType->name,
            'category' => $leaveType->category,
            'affects_leave_balance' => $leaveType->affects_leave_balance,
            'requires_approval' => $leaveType->requires_approval,
            'active' => false,
        ])
        ->assertOk()
        ->assertJsonPath('data.active', false);

    $this->assertDatabaseHas('leave_types', [
        'id' => $leaveType->id,
        'code' => 'annual_leave',
        'active' => false,
    ]);
});

it('mas company rekordjat tovabbra sem tudja modositani', function (): void {
    [$tenantA, $companyA] = $this->createTenantWithCompany();
    [$tenantB, $companyB] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($companyA);
    $foreign = LeaveType::factory()->create([
        'company_id' => $companyB->id,
        'code' => 'annual_leave',
    ]);

    $this->actingAsUserInCompany($user, $companyA)
        ->putJson(route('admin.leave_types.update', $foreign->id), [
            'code' => 'annual_leave_2',
            'name' => 'Tiltott update',
            'category' => $foreign->category,
            'affects_leave_balance' => $foreign->affects_leave_balance,
            'requires_approval' => $foreign->requires_approval,
            'active' => $foreign->active,
        ])
        ->assertNotFound();

    $this->assertDatabaseHas('leave_types', [
        'id' => $foreign->id,
        'company_id' => $companyB->id,
        'code' => 'annual_leave',
    ]);
});
