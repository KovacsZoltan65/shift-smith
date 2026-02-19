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

it('megtagadja a műszak törlést, ha nincs delete jogosultság', function (): void {
    $user = $this->createAdminUser();
    $user->syncPermissions([]);
    $user->syncRoles([]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $company = Company::factory()->create();
    $workShift = WorkShift::factory()->create(['company_id' => $company->id]);

    $this
        ->actingAs($user)
        ->deleteJson(route('work_shifts.destroy', ['id' => $workShift->id]))
        ->assertForbidden();
});

it('törli a műszakot adminnal és növeli a cache verziókat', function (): void {
    $user = $this->createAdminUser();

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $company = Company::factory()->create();
    $workShift = WorkShift::factory()->create(['company_id' => $company->id]);

    $versioner = app(CacheVersionService::class);
    Cache::forever('v:work_shifts.fetch', 1);
    Cache::forever('v:selectors.work_shifts', 1);

    $this
        ->actingAs($user)
        ->deleteJson(route('work_shifts.destroy', ['id' => $workShift->id]))
        ->assertOk();

    $this->assertSoftDeleted('work_shifts', ['id' => $workShift->id]);

    expect($versioner->get('work_shifts.fetch'))->toBe(2);
    expect($versioner->get('selectors.work_shifts'))->toBe(2);
});
