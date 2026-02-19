<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\User;
use App\Models\WorkPattern;
use App\Services\Cache\CacheVersionService;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('megtagadja a munkarend létrehozást jogosultság nélkül', function (): void {
    $user = User::factory()->create();
    $user->assignRole('user');

    $company = Company::factory()->create();

    $this->actingAs($user)
        ->postJson(route('work_patterns.store'), [
            'company_id' => $company->id,
            'name' => 'Nope',
            'type' => 'fixed_weekly',
        ])
        ->assertForbidden();
});

it('validálja a kötelező mezőket létrehozáskor', function (): void {
    $user = $this->createAdminUser();

    $this->actingAs($user)
        ->postJson(route('work_patterns.store'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['company_id', 'name', 'type']);
});

it('létrehozza a munkarendet és bumpolja a cache verziókat', function (): void {
    $user = $this->createAdminUser();

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $company = Company::factory()->create();
    $versioner = app(CacheVersionService::class);

    Cache::forever("v:work_patterns.fetch.company_{$company->id}", 1);
    Cache::forever("v:selectors.work_patterns.company_{$company->id}", 1);

    $payload = WorkPattern::factory()->make([
        'company_id' => $company->id,
        'name' => 'Nappali fix',
        'type' => 'fixed_weekly',
        'active' => true,
    ])->only([
        'company_id',
        'name',
        'type',
        'cycle_length_days',
        'weekly_minutes',
        'active',
        'meta',
    ]);

    $this->actingAs($user)
        ->postJson(route('work_patterns.store'), $payload)
        ->assertCreated()
        ->assertJsonStructure(['message', 'data' => ['id', 'company_id', 'name', 'type']]);

    $this->assertDatabaseHas('work_patterns', [
        'company_id' => $company->id,
        'name' => 'Nappali fix',
        'type' => 'fixed_weekly',
    ]);

    expect($versioner->get("work_patterns.fetch.company_{$company->id}"))->toBe(2);
    expect($versioner->get("selectors.work_patterns.company_{$company->id}"))->toBe(2);
});
