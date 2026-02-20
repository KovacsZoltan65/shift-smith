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
            'daily_work_minutes' => 480,
            'break_minutes' => 30,
        ])
        ->assertForbidden();
});

it('validálja a kötelező mezőket létrehozáskor', function (): void {
    $user = $this->createAdminUser();

    $this->actingAs($user)
        ->postJson(route('work_patterns.store'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['company_id', 'name', 'daily_work_minutes', 'break_minutes']);
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
        'daily_work_minutes' => 480,
        'break_minutes' => 30,
        'core_start_time' => null,
        'core_end_time' => null,
        'active' => true,
    ])->only([
        'company_id',
        'name',
        'daily_work_minutes',
        'break_minutes',
        'core_start_time',
        'core_end_time',
        'active',
    ]);

    $this->actingAs($user)
        ->postJson(route('work_patterns.store'), $payload)
        ->assertCreated()
        ->assertJsonStructure(['message', 'data' => ['id', 'company_id', 'name', 'daily_work_minutes']]);

    $this->assertDatabaseHas('work_patterns', [
        'company_id' => $company->id,
        'name' => 'Nappali fix',
        'daily_work_minutes' => 480,
        'break_minutes' => 30,
    ]);

    expect($versioner->get("work_patterns.fetch.company_{$company->id}"))->toBe(2);
    expect($versioner->get("selectors.work_patterns.company_{$company->id}"))->toBe(2);
});
