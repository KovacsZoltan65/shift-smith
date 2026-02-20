<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Position;
use App\Services\Cache\CacheVersionService;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('validálja a kötelező mezőt létrehozáskor', function (): void {
    $user = $this->createAdminUser();
    $company = Company::factory()->create();

    $this->actingAs($user)
        ->postJson(route('positions.store'), ['company_id' => $company->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('létrehozza a pozíciót és bumpolja a cache verziókat', function (): void {
    $user = $this->createAdminUser();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();
    $company = Company::factory()->create();

    $versioner = app(CacheVersionService::class);
    Cache::forever("v:company:{$company->id}:positions", 1);
    Cache::forever("v:selectors.positions.company_{$company->id}", 1);

    $this->actingAs($user)
        ->postJson(route('positions.store'), [
            'company_id' => $company->id,
            'name' => 'Teszt pozíció',
            'description' => 'Leírás',
            'active' => true,
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Teszt pozíció');

    $this->assertDatabaseHas('positions', ['company_id' => $company->id, 'name' => 'Teszt pozíció']);
    expect($versioner->get("company:{$company->id}:positions"))->toBe(2);
    expect($versioner->get("selectors.positions.company_{$company->id}"))->toBe(2);
});
