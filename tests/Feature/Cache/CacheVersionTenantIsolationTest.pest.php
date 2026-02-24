<?php

declare(strict_types=1);

use App\Models\TenantGroup;
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
