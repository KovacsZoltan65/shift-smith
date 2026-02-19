<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\User;
use App\Models\WorkShift;
use App\Services\Cache\CacheVersionService;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('megtagadja a műszak létrehozást, ha nincs create jogosultság', function (): void {
    /** @var User $user */
    $user = User::factory()->create();
    $user->assignRole('user');

    $company = Company::factory()->create();

    $this
        ->actingAs($user)
        ->postJson(route('work_shifts.store'), [
            'company_id' => $company->id,
            'name' => 'Nope',
            'start_time' => '08:00:00',
            'end_time' => '16:00:00',
            'work_time_minutes' => 480,
            'break_minutes' => 30,
            'active' => true,
        ])
        ->assertForbidden();
});

it('létrehozza a műszakot adminnal és növeli a cache verziókat', function (): void {
    $user = $this->createAdminUser();

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $company = Company::factory()->create();
    $versioner = app(CacheVersionService::class);

    Cache::forever('v:work_shifts.fetch', 1);
    Cache::forever('v:selectors.work_shifts', 1);

    $payload = WorkShift::factory()->make([
        'company_id' => $company->id,
        'name' => 'Teszt műszak',
        'start_time' => '08:00:00',
        'end_time' => '16:00:00',
        'work_time_minutes' => 450,
        'break_minutes' => 30,
        'active' => true,
    ])->only([
        'company_id',
        'name',
        'start_time',
        'end_time',
        'work_time_minutes',
        'break_minutes',
        'active',
    ]);

    $this
        ->actingAs($user)
        ->postJson(route('work_shifts.store'), $payload)
        ->assertOk();

    $this->assertDatabaseHas('work_shifts', [
        'company_id' => $company->id,
        'name' => 'Teszt műszak',
        'start_time' => '08:00:00',
        'end_time' => '16:00:00',
    ]);

    expect($versioner->get('work_shifts.fetch'))->toBe(2);
    expect($versioner->get('selectors.work_shifts'))->toBe(2);
});
