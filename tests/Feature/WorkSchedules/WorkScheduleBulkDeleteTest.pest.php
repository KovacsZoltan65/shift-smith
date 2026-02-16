<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\WorkSchedule;
use App\Services\Cache\CacheVersionService;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('denies bulk delete if user lacks permission', function (): void {
    $user = $this->createAdminUser();
    $user->syncPermissions([]);
    $user->syncRoles([]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $company = Company::factory()->create();
    $ws1 = WorkSchedule::factory()->create(['company_id' => $company->id, 'status' => 'draft']);
    $ws2 = WorkSchedule::factory()->create(['company_id' => $company->id, 'status' => 'draft']);

    $this
        ->actingAs($user)
        ->deleteJson(route('work_schedules.destroy_bulk'), ['ids' => [$ws1->id, $ws2->id]])
        ->assertForbidden();
});

it('prevents bulk delete if any published is included', function (): void {
    $user = $this->createAdminUser();

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $company = Company::factory()->create();
    $draft = WorkSchedule::factory()->create(['company_id' => $company->id, 'status' => 'draft']);
    $pub = WorkSchedule::factory()->create(['company_id' => $company->id, 'status' => 'published']);

    $this
        ->actingAs($user)
        ->deleteJson(route('work_schedules.destroy_bulk'), ['ids' => [$draft->id, $pub->id]])
        ->assertUnprocessable();

    $this->assertDatabaseHas('work_schedules', ['id' => $draft->id, 'deleted_at' => null]);
    $this->assertDatabaseHas('work_schedules', ['id' => $pub->id, 'deleted_at' => null]);
});

it('allows admin to bulk delete drafts and bumps cache versions', function (): void {
    $user = $this->createAdminUser();

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $company = Company::factory()->create();
    $ws = WorkSchedule::factory()->count(3)->create(['company_id' => $company->id, 'status' => 'draft']);

    $versioner = app(CacheVersionService::class);
    Cache::forever('v:work_schedules.fetch', 1);

    $ids = $ws->pluck('id')->all();

    $this
        ->actingAs($user)
        ->deleteJson(route('work_schedules.destroy_bulk'), ['ids' => $ids])
        ->assertOk()
        ->assertJson(['deleted' => 3]);

    foreach ($ids as $id) {
        $this->assertSoftDeleted('work_schedules', ['id' => $id]);
    }

    expect($versioner->get('work_schedules.fetch'))->toBe(2);
});
