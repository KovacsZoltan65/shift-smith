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
            'type' => 'fixed_weekly',
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
            'type' => 'fixed_weekly',
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
        'type' => 'custom',
    ]);

    $versioner = app(CacheVersionService::class);
    Cache::forever("v:work_patterns.fetch.company_{$company->id}", 1);
    Cache::forever("v:selectors.work_patterns.company_{$company->id}", 1);

    $this->actingAs($user)
        ->putJson(route('work_patterns.update', ['id' => $workPattern->id]), [
            'company_id' => $company->id,
            'name' => 'Új név',
            'type' => 'rotating_shifts',
            'cycle_length_days' => 14,
            'weekly_minutes' => 2400,
            'active' => true,
            'meta' => ['key' => 'value'],
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Új név');

    $this->assertDatabaseHas('work_patterns', [
        'id' => $workPattern->id,
        'name' => 'Új név',
        'type' => 'rotating_shifts',
    ]);

    expect($versioner->get("work_patterns.fetch.company_{$company->id}"))->toBe(2);
    expect($versioner->get("selectors.work_patterns.company_{$company->id}"))->toBe(2);
});
