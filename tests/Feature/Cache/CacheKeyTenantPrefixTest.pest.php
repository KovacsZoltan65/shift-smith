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

it('generates tenant prefixed cache key when tenant is current', function (): void {
    $tenant = TenantGroup::factory()->create();
    $tenant->makeCurrent();

    $service = app(CacheService::class);
    $ref = new ReflectionClass($service);
    $qualifyTag = $ref->getMethod('qualifyTag');
    $qualifyTag->setAccessible(true);
    $method = $ref->getMethod('qualifyKey');
    $method->setAccessible(true);

    /** @var string $tag */
    $tag = $qualifyTag->invoke($service, 'companies');
    /** @var string $key */
    $key = $method->invoke($service, $tag, 'abc');

    expect($key)->toStartWith("tenant:{$tenant->id}:companies:");
});

it('generates landlord prefixed cache key when no tenant is current', function (): void {
    TenantGroup::forgetCurrent();

    $service = app(CacheService::class);
    $ref = new ReflectionClass($service);
    $qualifyTag = $ref->getMethod('qualifyTag');
    $qualifyTag->setAccessible(true);
    $method = $ref->getMethod('qualifyKey');
    $method->setAccessible(true);

    /** @var string $tag */
    $tag = $qualifyTag->invoke($service, 'companies');
    /** @var string $key */
    $key = $method->invoke($service, $tag, 'abc');

    expect($key)->toStartWith('landlord:companies:');
    expect($key)->not->toStartWith('tenant:landlord:');
});
