<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\WorkSchedule;
use App\Models\User;
use App\Services\Cache\CacheVersionService;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('denies work schedule creation if user lacks permission', function (): void {
    /** @var User $user */
    $user = User::factory()->create();
    $user->assignRole('user');

    $company = Company::factory()->create();

    $this
        ->actingAs($user)
        ->postJson(route('work_schedules.store'), [
            'company_id' => $company->id,
            'name' => 'Nope',
            'date_from' => '2026-02-01',
            'date_to' => '2026-02-10',
            'status' => 'draft',
        ])
        ->assertForbidden();

    $this->assertDatabaseMissing('work_schedules', ['name' => 'Nope']);
});

it('validates required fields on store', function (): void {
    $user = $this->createAdminUser();

    $this
        ->actingAs($user)
        ->postJson(route('work_schedules.store'), [
            'company_id' => null,
            'name' => '',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['company_id', 'name', 'date_from', 'date_to', 'status']);
});

it('allows admin to store a work schedule and bumps cache versions', function (): void {
    $user = $this->createAdminUser();

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $company = Company::factory()->create();

    $versioner = app(CacheVersionService::class);

    Cache::forever('v:work_schedules.fetch', 1);

    $payload = WorkSchedule::factory()->make([
        'company_id' => $company->id,
        'status' => 'draft',
    ])->only(['company_id', 'name', 'date_from', 'date_to', 'status']);

    $payload['date_from'] = \Illuminate\Support\Carbon::parse((string) $payload['date_from'])->format('Y-m-d');
    $payload['date_to'] = \Illuminate\Support\Carbon::parse((string) $payload['date_to'])->format('Y-m-d');

    $this
        ->actingAs($user)
        ->postJson(route('work_schedules.store'), $payload)
        ->assertCreated();

    $this->assertDatabaseHas('work_schedules', [
        'company_id' => $company->id,
        'name' => $payload['name'],
        'status' => 'draft',
    ]);

    expect($versioner->get('work_schedules.fetch'))->toBe(2);
});
