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

it('megtagadja a törlést jogosultság nélkül', function (): void {
    $user = $this->createAdminUser();
    $user->syncRoles([]);
    $user->syncPermissions([]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $workPattern = WorkPattern::factory()->create();

    $this->actingAs($user)
        ->deleteJson(route('work_patterns.destroy', ['id' => $workPattern->id]))
        ->assertForbidden();
});

it('soft delete-olja a munkarendet és bumpolja a cache verziókat', function (): void {
    $user = $this->createAdminUser();

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $company = Company::factory()->create();
    $workPattern = WorkPattern::factory()->create(['company_id' => $company->id]);
    $versioner = app(CacheVersionService::class);

    Cache::forever("v:work_patterns.fetch.company_{$company->id}", 1);
    Cache::forever("v:selectors.work_patterns.company_{$company->id}", 1);

    $this->actingAs($user)
        ->deleteJson(route('work_patterns.destroy', ['id' => $workPattern->id]))
        ->assertOk();

    $this->assertSoftDeleted('work_patterns', ['id' => $workPattern->id]);
    expect($versioner->get("work_patterns.fetch.company_{$company->id}"))->toBe(2);
    expect($versioner->get("selectors.work_patterns.company_{$company->id}"))->toBe(2);
});
