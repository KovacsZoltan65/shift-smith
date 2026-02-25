<?php

declare(strict_types=1);

use App\Models\WorkSchedule;
use App\Services\Cache\CacheVersionService;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('denies work schedule update if user lacks permission', function (): void {
    [, $company] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($company);
    $user->syncPermissions([]);
    $user->syncRoles([]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $ws = WorkSchedule::factory()->create(['company_id' => $company->id]);

    $this
        ->actingAsUserInCompany($user, $company)
        ->putJson(route('work_schedules.update', ['id' => $ws->id]), [
            'company_id' => $company->id,
            'name' => 'X',
            'date_from' => '2026-02-01',
            'date_to' => '2026-02-10',
            'status' => 'draft',
        ])
        ->assertForbidden();
});

it('validates date_to >= date_from on update', function (): void {
    [, $company] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($company);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $ws = WorkSchedule::factory()->create(['company_id' => $company->id]);

    $this
        ->actingAsUserInCompany($user, $company)
        ->putJson(route('work_schedules.update', ['id' => $ws->id]), [
            'company_id' => $company->id,
            'name' => 'Bad',
            'date_from' => '2026-02-10',
            'date_to' => '2026-02-01',
            'status' => 'draft',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['date_to']);
});

it('allows admin to update and bumps cache versions', function (): void {
    [, $company] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($company);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $ws = WorkSchedule::factory()->create(['company_id' => $company->id, 'name' => 'Old', 'status' => 'draft']);

    $versioner = app(CacheVersionService::class);
    Cache::forever('v:work_schedules.fetch', 1);

    $this
        ->actingAsUserInCompany($user, $company)
        ->putJson(route('work_schedules.update', ['id' => $ws->id]), [
            'company_id' => $company->id,
            'name' => 'New Name',
            'date_from' => $ws->date_from->format('Y-m-d'),
            'date_to' => $ws->date_to->format('Y-m-d'),
            'status' => 'draft',
        ])
        ->assertOk();

    $this->assertDatabaseHas('work_schedules', [
        'id' => $ws->id,
        'name' => 'New Name',
    ]);

    expect($versioner->get('work_schedules.fetch'))->toBe(2);
});
