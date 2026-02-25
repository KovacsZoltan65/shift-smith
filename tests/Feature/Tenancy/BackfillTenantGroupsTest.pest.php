<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\TenantGroup;

it('backfills tenant groups for companies and is idempotent', function (): void {
    $this->artisan('migrate:fresh', [
        '--path' => 'database/migrations/2026_01_22_094033_create_companies_table.php',
    ])->assertSuccessful();

    $this->artisan('migrate', [
        '--path' => 'database/migrations/2026_02_24_100000_create_tenant_groups_table.php',
    ])->assertSuccessful();

    $this->artisan('migrate', [
        '--path' => 'database/migrations/2026_02_24_100100_add_tenant_group_id_to_companies_table.php',
    ])->assertSuccessful();

    Company::factory()->create(['name' => 'Alpha Kft']);
    Company::factory()->create(['name' => 'Alpha Kft']);
    Company::factory()->create(['name' => 'Beta Kft']);

    $this->artisan('tenancy:backfill-tenant-groups')
        ->assertSuccessful();

    expect(TenantGroup::query()->count())->toBe(Company::query()->count());
    expect(Company::query()->whereNull('tenant_group_id')->count())->toBe(0);
    expect(TenantGroup::query()->distinct('slug')->count('slug'))->toBe(TenantGroup::query()->count());

    $tenantGroupCountAfterFirstRun = TenantGroup::query()->count();

    $this->artisan('tenancy:backfill-tenant-groups')
        ->assertSuccessful();

    expect(TenantGroup::query()->count())->toBe($tenantGroupCountAfterFirstRun);
});
