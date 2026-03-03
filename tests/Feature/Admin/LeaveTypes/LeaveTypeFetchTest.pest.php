<?php

declare(strict_types=1);

use App\Models\LeaveType;
use Spatie\Permission\PermissionRegistrar;

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

it('permission nelkul 403-at ad a fetch endpointre', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($company);
    $user->syncRoles([]);
    $user->syncPermissions(['companies.view']);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $this->actingAsUserInCompany($user, $company)
        ->getJson(route('admin.leave_types.fetch'))
        ->assertForbidden();
});

it('azonos tenanten belul is csak a current company rekordjai latszanak', function (): void {
    [$tenant, $companyA] = $this->createTenantWithCompany();
    [, $companyB] = $this->createTenantWithCompany([], ['tenant_group_id' => $tenant->id]);
    $user = $this->createAdminUser($companyA);

    $visible = LeaveType::factory()->create([
        'company_id' => $companyA->id,
        'code' => 'company-a',
    ]);

    LeaveType::factory()->create([
        'company_id' => $companyB->id,
        'code' => 'company-b',
    ]);

    $this->actingAsUserInCompany($user, $companyA)
        ->getJson(route('admin.leave_types.fetch'))
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('items.0.id', $visible->id)
        ->assertJsonMissing(['code' => 'company-b']);
});
