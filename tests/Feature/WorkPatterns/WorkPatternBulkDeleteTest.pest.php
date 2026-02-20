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

it('megtagadja a bulk törlést jogosultság nélkül', function (): void {
    $user = $this->createAdminUser();
    $user->syncRoles([]);
    $user->syncPermissions([]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $ids = WorkPattern::factory()->count(2)->create()->pluck('id')->all();

    $this->actingAs($user)
        ->deleteJson(route('work_patterns.destroy_bulk'), [
            'ids' => $ids,
            'company_id' => WorkPattern::query()->findOrFail($ids[0])->company_id,
        ])
        ->assertForbidden();
});

it('soft delete-olja a kiválasztott munkarendeket és bumpolja a cache verziókat', function (): void {
    $user = $this->createAdminUser();

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $company = Company::factory()->create();
    $rows = WorkPattern::factory()->count(3)->create(['company_id' => $company->id]);
    $ids = $rows->pluck('id')->all();

    $versioner = app(CacheVersionService::class);
    Cache::forever("v:work_patterns.fetch.company_{$company->id}", 1);
    Cache::forever("v:selectors.work_patterns.company_{$company->id}", 1);

    $this->actingAs($user)
        ->deleteJson(route('work_patterns.destroy_bulk'), [
            'ids' => $ids,
            'company_id' => $company->id,
        ])
        ->assertOk()
        ->assertJsonPath('deleted', 3);

    foreach ($ids as $id) {
        $this->assertSoftDeleted('work_patterns', ['id' => $id]);
    }

    expect($versioner->get("work_patterns.fetch.company_{$company->id}"))->toBe(2);
    expect($versioner->get("selectors.work_patterns.company_{$company->id}"))->toBe(2);
});
