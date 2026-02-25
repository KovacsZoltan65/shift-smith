<?php

declare(strict_types=1);

use App\Models\TenantGroup;
use App\Services\CacheService;

beforeEach(function (): void {
    TenantGroup::forgetCurrent();
});

afterEach(function (): void {
    TenantGroup::forgetCurrent();
});

function invokeQualifyKey(CacheService $service, string $tag, string $key): string
{
    $ref = new ReflectionClass($service);
    $tagMethod = $ref->getMethod('qualifyTag');
    $tagMethod->setAccessible(true);
    $method = $ref->getMethod('qualifyKey');
    $method->setAccessible(true);

    /** @var string $qualifiedTag */
    $qualifiedTag = $tagMethod->invoke($service, $tag);

    /** @var string $qualified */
    $qualified = $method->invoke($service, $qualifiedTag, $key);

    return $qualified;
}

it('uses tenant prefix in tenant context', function (): void {
    $tenant = TenantGroup::factory()->create();
    $tenant->makeCurrent();

    $service = app(CacheService::class);
    $key = invokeQualifyKey($service, 'companies', 'v1:abc');

    expect($key)->toStartWith("tenant:{$tenant->id}:companies:");
});

it('uses landlord prefix when no tenant is current', function (): void {
    TenantGroup::forgetCurrent();

    $service = app(CacheService::class);
    $key = invokeQualifyKey($service, 'companies', 'v1:abc');

    expect($key)->toStartWith('landlord:companies:');
});

it('generates different keys for two tenants with same tag and input', function (): void {
    $tenantOne = TenantGroup::factory()->create();
    $tenantTwo = TenantGroup::factory()->create();
    $service = app(CacheService::class);

    $tenantOne->makeCurrent();
    $keyOne = invokeQualifyKey($service, 'companies', 'v1:abc');

    $tenantTwo->makeCurrent();
    $keyTwo = invokeQualifyKey($service, 'companies', 'v1:abc');

    expect($keyOne)->not->toBe($keyTwo);
    expect($keyOne)->toStartWith("tenant:{$tenantOne->id}:companies:");
    expect($keyTwo)->toStartWith("tenant:{$tenantTwo->id}:companies:");
});
