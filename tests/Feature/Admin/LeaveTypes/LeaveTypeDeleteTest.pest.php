<?php

declare(strict_types=1);

use App\Models\LeaveType;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('soft delete-olja a sajat company leave type rekordjat', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($company);
    $leaveType = LeaveType::factory()->create([
        'company_id' => $company->id,
    ]);

    $this->actingAsUserInCompany($user, $company)
        ->deleteJson(route('admin.leave_types.destroy', $leaveType->id))
        ->assertOk()
        ->assertJsonPath('deleted', true);

    $this->assertSoftDeleted('leave_types', [
        'id' => $leaveType->id,
        'company_id' => $company->id,
    ]);
});

it('nem torli masik tenant group rekordjat', function (): void {
    [$tenantA, $companyA] = $this->createTenantWithCompany();
    [$tenantB, $companyB] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($companyA);
    $foreign = LeaveType::factory()->create([
        'company_id' => $companyB->id,
    ]);

    $this->actingAsUserInCompany($user, $companyA)
        ->deleteJson(route('admin.leave_types.destroy', $foreign->id))
        ->assertNotFound();

    $this->assertDatabaseHas('leave_types', [
        'id' => $foreign->id,
        'company_id' => $companyB->id,
        'deleted_at' => null,
    ]);
});
