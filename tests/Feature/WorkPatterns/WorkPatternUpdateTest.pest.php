<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\WorkPattern;
use App\Services\Cache\CacheVersionService;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('megtagadja a munkarend módosítást jogosultság nélkül', function (): void {
    $user = $this->createAdminUser();
    $user->syncRoles([]);
    $user->syncPermissions([]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $workPattern = WorkPattern::factory()->create();

    $this->actingAs($user)
        ->putJson(route('work_patterns.update', ['id' => $workPattern->id]), [
            'company_id' => $workPattern->company_id,
            'name' => 'Tiltott módosítás',
            'daily_work_minutes' => 480,
            'break_minutes' => 30,
        ])
        ->assertForbidden();
});

it('validálja az egyedi nevet company scope-ban', function (): void {
    $user = $this->createAdminUser();
    $company = Company::factory()->create();

    WorkPattern::factory()->create([
        'company_id' => $company->id,
        'name' => 'Duplikált név',
    ]);

    $target = WorkPattern::factory()->create([
        'company_id' => $company->id,
        'name' => 'Másik név',
    ]);

    $this->actingAs($user)
        ->putJson(route('work_patterns.update', ['id' => $target->id]), [
            'company_id' => $company->id,
            'name' => 'Duplikált név',
            'daily_work_minutes' => 480,
            'break_minutes' => 30,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('frissíti a munkarendet és bumpolja a cache verziókat', function (): void {
    $user = $this->createAdminUser();

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $company = Company::factory()->create();
    $workPattern = WorkPattern::factory()->create([
        'company_id' => $company->id,
        'name' => 'Régi név',
        'daily_work_minutes' => 420,
        'break_minutes' => 20,
    ]);

    $versioner = app(CacheVersionService::class);
    Cache::forever("v:company:{$company->id}:work_patterns", 1);
    Cache::forever('v:selectors.work_patterns', 1);

    $this->actingAs($user)
        ->putJson(route('work_patterns.update', ['id' => $workPattern->id]), [
            'company_id' => $company->id,
            'name' => 'Új név',
            'daily_work_minutes' => 720,
            'break_minutes' => 60,
            'core_start_time' => '10:00',
            'core_end_time' => '15:00',
            'active' => true,
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Új név');

    $this->assertDatabaseHas('work_patterns', [
        'id' => $workPattern->id,
        'name' => 'Új név',
        'daily_work_minutes' => 720,
        'break_minutes' => 60,
        'core_start_time' => '10:00:00',
        'core_end_time' => '15:00:00',
    ]);

    expect($versioner->get("company:{$company->id}:work_patterns"))->toBe(2);
    expect($versioner->get('selectors.work_patterns'))->toBe(2);
});
