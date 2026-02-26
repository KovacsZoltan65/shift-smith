<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\TenantGroup;

it('backfills tenant groups for companies and is idempotent', function (): void {
    $tenantA = TenantGroup::factory()->create([
        'name' => 'Tenant A',
        'slug' => 'tenant-a',
    ]);
    $tenantB = TenantGroup::factory()->create([
        'name' => 'Tenant B',
        'slug' => 'tenant-b',
    ]);

    Company::factory()->create([
        'name' => 'Company A',
        'tenant_group_id' => $tenantA->id,
    ]);
    Company::factory()->create([
        'name' => 'Company B',
        'tenant_group_id' => $tenantB->id,
    ]);

    $tenantGroupCountBefore = TenantGroup::query()->count();
    $companyCountBefore = Company::query()->count();

    $this->artisan('tenancy:backfill-tenant-groups')
        ->assertSuccessful();

    expect(TenantGroup::query()->count())->toBe($tenantGroupCountBefore);
    expect(Company::query()->count())->toBe($companyCountBefore);
    expect(Company::query()->whereNull('tenant_group_id')->count())->toBe(0);
    expect(Company::query()->whereNotNull('tenant_group_id')->count())->toBe($companyCountBefore);

    $tenantGroupCountAfterFirstRun = TenantGroup::query()->count();

    $this->artisan('tenancy:backfill-tenant-groups')
        ->assertSuccessful();

    expect(TenantGroup::query()->count())->toBe($tenantGroupCountAfterFirstRun);
});
