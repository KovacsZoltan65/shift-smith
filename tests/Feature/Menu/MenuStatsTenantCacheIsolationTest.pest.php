<?php

declare(strict_types=1);

use App\Models\TenantGroup;
use App\Models\User;
use App\Models\UserMenuStat;
use App\Services\Cache\CacheVersionService;
use App\Services\Menu\MenuContextService;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    TenantGroup::forgetCurrent();
    Cache::flush();
});

afterEach(function (): void {
    TenantGroup::forgetCurrent();
});

it('keeps menu.context cache isolated between tenant groups', function (): void {
    $tenantOne = TenantGroup::factory()->create();
    $tenantTwo = TenantGroup::factory()->create();
    $menuContext = app(MenuContextService::class);

    /** @var User $user */
    $user = User::factory()->create();

    UserMenuStat::query()->create([
        'user_id' => $user->id,
        'menu_key' => 'dashboard',
        'hit_count' => 10,
        'last_used_at' => now()->subMinute(),
    ]);

    $tenantOne->makeCurrent();
    $tenantOneMenuOrder = $menuContext->getMenuOrderForUser((int) $user->id);

    UserMenuStat::query()->updateOrCreate(
        ['user_id' => $user->id, 'menu_key' => 'dashboard'],
        ['hit_count' => 1, 'last_used_at' => now()]
    );
    UserMenuStat::query()->updateOrCreate(
        ['user_id' => $user->id, 'menu_key' => 'users.index'],
        ['hit_count' => 50, 'last_used_at' => now()]
    );

    $tenantTwo->makeCurrent();
    $tenantTwoMenuOrder = $menuContext->getMenuOrderForUser((int) $user->id);

    expect($tenantOneMenuOrder[0] ?? null)->toBe('dashboard');
    expect($tenantTwoMenuOrder[0] ?? null)->toBe('users.index');
});

it('bumps menu.context cache version only in current tenant', function (): void {
    $tenantOne = TenantGroup::factory()->create();
    $tenantTwo = TenantGroup::factory()->create();

    /** @var User $user */
    $user = User::factory()->create();

    $versioner = app(CacheVersionService::class);
    $menuContext = app(MenuContextService::class);

    $tenantOne->makeCurrent();
    $tenantOneBefore = $versioner->get('menu.context');
    $menuContext->trackMenuUsage((int) $user->id, 'dashboard');
    $tenantOneAfter = $versioner->get('menu.context');

    $tenantTwo->makeCurrent();
    $tenantTwoVersion = $versioner->get('menu.context');

    expect($tenantOneAfter)->toBeGreaterThan($tenantOneBefore);
    expect($tenantTwoVersion)->toBe($tenantOneBefore);
    expect($tenantTwoVersion)->not->toBe($tenantOneAfter);
});
