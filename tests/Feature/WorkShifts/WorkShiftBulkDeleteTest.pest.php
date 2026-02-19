<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\WorkShift;
use App\Services\Cache\CacheVersionService;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('megtagadja a tömeges törlést, ha nincs deleteAny jogosultság', function (): void {
    $user = $this->createAdminUser();
    $user->syncPermissions([]);
    $user->syncRoles([]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $company = Company::factory()->create();
    $ws1 = WorkShift::factory()->create(['company_id' => $company->id]);
    $ws2 = WorkShift::factory()->create(['company_id' => $company->id]);

    $this
        ->actingAs($user)
        ->deleteJson(route('work_shifts.destroy_bulk'), ['ids' => [$ws1->id, $ws2->id]])
        ->assertForbidden();
});

it('adminnal tömegesen töröl és növeli a cache verziókat', function (): void {
    $user = $this->createAdminUser();

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $company = Company::factory()->create();
    $ws = WorkShift::factory()->count(3)->create(['company_id' => $company->id]);

    $versioner = app(CacheVersionService::class);
    Cache::forever('v:work_shifts.fetch', 1);
    Cache::forever('v:selectors.work_shifts', 1);

    $ids = $ws->pluck('id')->all();

    $this
        ->actingAs($user)
        ->deleteJson(route('work_shifts.destroy_bulk'), ['ids' => $ids])
        ->assertOk()
        ->assertJson(['deleted' => 3]);

    foreach ($ids as $id) {
        $this->assertSoftDeleted('work_shifts', ['id' => $id]);
    }

    expect($versioner->get('work_shifts.fetch'))->toBe(2);
    expect($versioner->get('selectors.work_shifts'))->toBe(2);
});
