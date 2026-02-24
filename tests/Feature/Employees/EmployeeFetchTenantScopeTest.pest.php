<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Employee;
use App\Models\TenantGroup;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
    TenantGroup::forgetCurrent();
});

afterEach(function (): void {
    TenantGroup::forgetCurrent();
});

it('does not return tenant-foreign employees when company_id points to another tenant', function (): void {
    $tenantOne = TenantGroup::factory()->create();
    $tenantTwo = TenantGroup::factory()->create();

    $companyOne = Company::factory()->create(['tenant_group_id' => $tenantOne->id]);
    $companyTwo = Company::factory()->create(['tenant_group_id' => $tenantTwo->id]);

    $tenantTwoEmployee = Employee::factory()->create([
        'company_id' => $companyTwo->id,
        'first_name' => 'Cross',
        'last_name' => 'Tenant',
        'email' => 'cross.tenant@example.com',
    ]);

    $user = $this->createAdminUser($companyOne);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $response = $this->actingAs($user)
        ->withSession(['current_tenant_group_id' => $tenantOne->id])
        ->getJson(route('employees.fetch', [
            'company_id' => $companyTwo->id,
            'page' => 1,
            'per_page' => 50,
            'order' => 'desc',
        ]));

    $response->assertOk();
    $response->assertJsonCount(0, 'data');

    $ids = array_map('intval', array_column($response->json('data'), 'id'));
    expect($ids)->not->toContain($tenantTwoEmployee->id);
});
