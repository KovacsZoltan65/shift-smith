<?php

declare(strict_types=1);

use App\Models\WorkSchedule;
use App\Services\Cache\CacheVersionService;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('denies delete if user lacks permission', function (): void {
    [, $company] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($company);
    $user->syncPermissions([]);
    $user->syncRoles([]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $ws = WorkSchedule::factory()->create(['company_id' => $company->id]);

    $this
        ->actingAsUserInCompany($user, $company)
        ->deleteJson(route('work_schedules.destroy', ['id' => $ws->id]))
        ->assertForbidden();
});

it('prevents deleting published work schedule', function (): void {
    [, $company] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($company);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $ws = WorkSchedule::factory()->create(['company_id' => $company->id, 'status' => 'published']);

    $this
        ->actingAsUserInCompany($user, $company)
        ->deleteJson(route('work_schedules.destroy', ['id' => $ws->id]))
        ->assertUnprocessable();

    $this->assertDatabaseHas('work_schedules', ['id' => $ws->id, 'deleted_at' => null]);
});

it('allows admin to delete draft and bumps cache versions', function (): void {
    [, $company] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($company);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $ws = WorkSchedule::factory()->create(['company_id' => $company->id, 'status' => 'draft']);

    $versioner = app(CacheVersionService::class);
    Cache::forever('v:work_schedules.fetch', 1);

    $this
        ->actingAsUserInCompany($user, $company)
        ->deleteJson(route('work_schedules.destroy', ['id' => $ws->id]))
        ->assertOk();

    $this->assertSoftDeleted('work_schedules', ['id' => $ws->id]);
    expect($versioner->get('work_schedules.fetch'))->toBe(2);
});
