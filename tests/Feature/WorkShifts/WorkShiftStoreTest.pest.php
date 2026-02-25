<?php

declare(strict_types=1);

use App\Models\Company;
use App\Services\Cache\CacheVersionService;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('forbids work shift store without create permission', function (): void {
    $company = Company::factory()->create();
    $user = $this->createAdminUser($company);
    $user->syncRoles([]);
    $user->assignRole('user');

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $this->actingAs($user)
        ->withSession(['current_company_id' => $company->id])
        ->postJson(route('work_shifts.store'), [
            'name' => 'Reggel',
            'start_time' => '08:00',
            'end_time' => '16:00',
            'active' => true,
        ])
        ->assertForbidden();
});

it('validates required fields and end_time rule on store', function (): void {
    $company = Company::factory()->create();
    $user = $this->createAdminUser($company);

    $this->actingAs($user)
        ->withSession(['current_company_id' => $company->id])
        ->postJson(route('work_shifts.store'), [
            'name' => '',
            'start_time' => '16:00',
            'end_time' => '08:00',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'end_time']);
});

it('stores shift in current company and bumps fetch+selector cache versions', function (): void {
    $company = Company::factory()->create();
    $user = $this->createAdminUser($company);
    $versioner = app(CacheVersionService::class);

    $beforeFetch = $versioner->get('work_shifts.fetch');
    $beforeSelector = $versioner->get('selectors.work_shifts');

    $this->actingAs($user)
        ->withSession(['current_company_id' => $company->id])
        ->postJson(route('work_shifts.store'), [
            'name' => 'Délelőtt',
            'start_time' => '08:00',
            'end_time' => '12:00',
            'work_time_minutes' => 240,
            'break_minutes' => 15,
            'active' => true,
        ])
        ->assertCreated();

    $this->assertDatabaseHas('work_shifts', [
        'company_id' => $company->id,
        'name' => 'Délelőtt',
    ]);

    expect($versioner->get('work_shifts.fetch'))->toBeGreaterThan($beforeFetch);
    expect($versioner->get('selectors.work_shifts'))->toBeGreaterThan($beforeSelector);
});
