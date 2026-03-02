<?php

declare(strict_types=1);

use App\Models\LeaveType;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('csak az aktualis company leave type rekordjait adja vissza', function (): void {
    [$tenantA, $companyA] = $this->createTenantWithCompany();
    [$tenantB, $companyB] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($companyA);

    $visible = LeaveType::factory()->create([
        'company_id' => $companyA->id,
        'code' => 'annual',
        'name' => 'Szabadsag',
    ]);

    LeaveType::factory()->create([
        'company_id' => $companyB->id,
        'code' => 'foreign',
        'name' => 'Masik tenant',
    ]);

    $this->actingAsUserInCompany($user, $companyA)
        ->getJson(route('admin.leave_types.fetch'))
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('items.0.id', $visible->id)
        ->assertJsonPath('items.0.company_id', $companyA->id)
        ->assertJsonMissing(['code' => 'foreign']);
});

it('mas tenant group company rekordja show endpointen nem latszik', function (): void {
    [$tenantA, $companyA] = $this->createTenantWithCompany();
    [$tenantB, $companyB] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($companyA);

    $foreign = LeaveType::factory()->create([
        'company_id' => $companyB->id,
    ]);

    $this->actingAsUserInCompany($user, $companyA)
        ->getJson(route('admin.leave_types.show', $foreign->id))
        ->assertNotFound();
});
