<?php

declare(strict_types=1);

use App\Models\AppSetting;
use App\Models\TenantGroup;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('átirányítja a vendéget az app settings fetch végpontról', function (): void {
    $this->get(route('admin.app_settings.fetch'))->assertRedirect();
});

it('megtagadja a fetch-et, ha nincs viewAny jogosultság', function (): void {
    $user = $this->createAdminUser();
    $user->syncRoles([]);
    $user->syncPermissions([]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $this->actingAs($user)
        ->getJson(route('admin.app_settings.fetch'))
        ->assertForbidden();
});

it('landlord scope-ban listáz tenant filter nélkül és támogatja a szűrést', function (): void {
    $user = $this->createAdminUser();
    $company = $user->companies()->firstOrFail();
    TenantGroup::query()->findOrFail((int) $company->tenant_group_id)->makeCurrent();

    AppSetting::query()->create([
        'key' => 'leave.max_days',
        'value' => 12,
        'type' => 'int',
        'group' => 'leave',
        'label' => 'Leave days',
    ]);

    AppSetting::query()->create([
        'key' => 'ui.dashboard',
        'value' => ['cards' => true],
        'type' => 'json',
        'group' => 'ui',
        'label' => 'Dashboard UI',
    ]);

    $response = $this->actingAs($user)
        ->getJson(route('admin.app_settings.fetch', [
            'q' => 'leave',
            'sortBy' => 'key',
            'sortDir' => 'asc',
            'page' => 1,
            'perPage' => 10,
        ]));

    $response
        ->assertOk()
        ->assertJsonStructure([
            'items',
            'meta' => ['current_page', 'per_page', 'total', 'last_page'],
            'filter',
            'options' => ['groups', 'types'],
        ]);

    expect($response->json('items'))->toHaveCount(1);
    expect($response->json('items.0.key'))->toBe('leave.max_days');

    $all = $this->actingAs($user)
        ->getJson(route('admin.app_settings.fetch', ['sortBy' => 'key', 'sortDir' => 'asc']))
        ->assertOk();

    expect($all->json('meta.total'))->toBe(2);
    expect($all->json('options.groups'))->toContain('leave', 'ui');
});
