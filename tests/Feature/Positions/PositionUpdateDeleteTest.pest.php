<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Position;
use App\Services\Cache\CacheVersionService;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('frissíti a pozíciót', function (): void {
    $user = $this->createAdminUser();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();
    $company = Company::factory()->create();

    $position = Position::factory()->create(['company_id' => $company->id, 'name' => 'Régi']);

    $this->actingAs($user)
        ->putJson(route('positions.update', $position->id), [
            'company_id' => $company->id,
            'name' => 'Új',
            'description' => 'Leírás',
            'active' => true,
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Új');

    $this->assertDatabaseHas('positions', ['id' => $position->id, 'company_id' => $company->id, 'name' => 'Új']);
});

it('törli a pozíciót és bumpolja a cache verziókat', function (): void {
    $user = $this->createAdminUser();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();
    $company = Company::factory()->create();

    $position = Position::factory()->create(['company_id' => $company->id]);
    $versioner = app(CacheVersionService::class);
    Cache::forever('v:positions.fetch', 1);
    Cache::forever("v:selectors.positions.company_{$company->id}", 1);

    $this->actingAs($user)
        ->deleteJson(route('positions.destroy', $position->id), ['company_id' => $company->id])
        ->assertOk();

    $this->assertSoftDeleted('positions', ['id' => $position->id]);
    expect($versioner->get('positions.fetch'))->toBe(2);
    expect($versioner->get("selectors.positions.company_{$company->id}"))->toBe(2);
});
