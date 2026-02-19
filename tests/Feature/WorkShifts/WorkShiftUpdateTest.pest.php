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

it('megtagadja a műszak frissítést, ha nincs update jogosultság', function (): void {
    $user = $this->createAdminUser();
    $user->syncPermissions([]);
    $user->syncRoles([]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $company = Company::factory()->create();
    $workShift = WorkShift::factory()->create(['company_id' => $company->id]);

    $this
        ->actingAs($user)
        ->putJson(route('work_shifts.update', ['id' => $workShift->id]), [
            'company_id' => $company->id,
            'name' => 'X',
            'start_time' => '07:00:00',
            'end_time' => '15:00:00',
            'work_time_minutes' => 450,
            'break_minutes' => 30,
            'active' => true,
        ])
        ->assertForbidden();
});

it('frissíti a műszakot adminnal és növeli a cache verziókat', function (): void {
    $user = $this->createAdminUser();

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $company = Company::factory()->create();
    $workShift = WorkShift::factory()->create([
        'company_id' => $company->id,
        'name' => 'Régi műszak',
        'start_time' => '06:00:00',
        'end_time' => '14:00:00',
    ]);

    $versioner = app(CacheVersionService::class);
    Cache::forever('v:work_shifts.fetch', 1);
    Cache::forever('v:selectors.work_shifts', 1);

    $this
        ->actingAs($user)
        ->putJson(route('work_shifts.update', ['id' => $workShift->id]), [
            'company_id' => $company->id,
            'name' => 'Új műszak',
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'work_time_minutes' => 450,
            'break_minutes' => 30,
            'active' => true,
        ])
        ->assertOk();

    $this->assertDatabaseHas('work_shifts', [
        'id' => $workShift->id,
        'name' => 'Új műszak',
        'start_time' => '09:00:00',
        'end_time' => '17:00:00',
    ]);

    expect($versioner->get('work_shifts.fetch'))->toBe(2);
    expect($versioner->get('selectors.work_shifts'))->toBe(2);
});
