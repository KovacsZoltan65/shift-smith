<?php

declare(strict_types=1);

use App\Models\TenantGroup;
use App\Models\Company;
use App\Services\Cache\CacheNamespaces;
use App\Services\Cache\CacheVersionService;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    TenantGroup::forgetCurrent();
    Cache::flush();
});

afterEach(function (): void {
    TenantGroup::forgetCurrent();
});

it('isolates cache version bump between tenant groups', function (): void {
    $versioner = app(CacheVersionService::class);

    $tenantOne = TenantGroup::factory()->create();
    $tenantTwo = TenantGroup::factory()->create();

    $tenantOne->makeCurrent();
    $tenantOneBefore = $versioner->get('companies.fetch');
    $tenantOneAfterBump = $versioner->bump('companies.fetch');

    $tenantTwo->makeCurrent();
    $tenantTwoVersion = $versioner->get('companies.fetch');

    expect($tenantOneAfterBump)->toBeGreaterThan($tenantOneBefore);
    expect($tenantTwoVersion)->toBe($tenantOneBefore);
    expect($tenantTwoVersion)->not->toBe($tenantOneAfterBump);
});

it('uses landlord namespace when there is no current tenant', function (): void {
    $versioner = app(CacheVersionService::class);

    TenantGroup::forgetCurrent();
    $landlordBefore = $versioner->get('companies.fetch');
    $landlordAfterBump = $versioner->bump('companies.fetch');

    $tenant = TenantGroup::factory()->create();
    $tenant->makeCurrent();
    $tenantVersion = $versioner->get('companies.fetch');

    expect($landlordAfterBump)->toBeGreaterThan($landlordBefore);
    expect($tenantVersion)->toBe($landlordBefore);
    expect($tenantVersion)->not->toBe($landlordAfterBump);
});

it('isolates tenant work schedules namespace between tenant groups', function (): void {
    $versioner = app(CacheVersionService::class);

    $tenantOne = TenantGroup::factory()->create();
    $tenantTwo = TenantGroup::factory()->create();

    $namespaceOne = CacheNamespaces::tenantWorkSchedules($tenantOne->id);
    $namespaceTwo = CacheNamespaces::tenantWorkSchedules($tenantTwo->id);

    $beforeOne = $versioner->get($namespaceOne);
    $afterOne = $versioner->bump($namespaceOne);
    $currentTwo = $versioner->get($namespaceTwo);

    expect($afterOne)->toBeGreaterThan($beforeOne);
    expect($currentTwo)->toBe($beforeOne);
    expect($currentTwo)->not->toBe($afterOne);
});

it('isolates company scoped work_schedules namespace by tenant group', function (): void {
    $versioner = app(CacheVersionService::class);

    $tenantOne = TenantGroup::factory()->create();
    $tenantTwo = TenantGroup::factory()->create();

    $companyOne = Company::factory()->create(['tenant_group_id' => $tenantOne->id]);
    $companyTwo = Company::factory()->create(['tenant_group_id' => $tenantTwo->id]);

    $tenantOne->makeCurrent();
    $namespaceOne = "company:{$companyOne->id}:work_schedules";
    $beforeOne = $versioner->get($namespaceOne);
    $afterOne = $versioner->bump($namespaceOne);

    $tenantTwo->makeCurrent();
    $namespaceTwo = "company:{$companyTwo->id}:work_schedules";
    $currentTwo = $versioner->get($namespaceTwo);

    expect($afterOne)->toBeGreaterThan($beforeOne);
    expect($currentTwo)->toBe($beforeOne);
    expect($currentTwo)->not->toBe($afterOne);
});
