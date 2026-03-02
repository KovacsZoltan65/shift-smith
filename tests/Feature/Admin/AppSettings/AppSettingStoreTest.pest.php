<?php

declare(strict_types=1);

use App\Models\TenantGroup;
use App\Services\Cache\CacheVersionService;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('megtagadja a létrehozást, ha nincs create jogosultság', function (): void {
    $user = $this->createAdminUser();
    $user->syncRoles([]);
    $user->syncPermissions([]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $this->actingAs($user)
        ->postJson(route('admin.app_settings.store'), [
            'key' => 'leave.cutoff',
            'type' => 'int',
            'group' => 'leave',
            'value' => 5,
        ])
        ->assertForbidden();
});

it('validálja a value mezőt típus szerint', function (): void {
    $user = $this->createAdminUser();

    $this->actingAs($user)
        ->postJson(route('admin.app_settings.store'), [
            'key' => 'ui.invalid_json',
            'type' => 'json',
            'group' => 'ui',
            'value' => '{not-json}',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['value']);
});

it('ment landlord app settinget és landlord cache verziót bumpol tenant kontextusban is', function (): void {
    $user = $this->createAdminUser();
    $company = $user->companies()->firstOrFail();
    TenantGroup::query()->findOrFail((int) $company->tenant_group_id)->makeCurrent();

    $versioner = app(CacheVersionService::class);
    $beforeFetch = $versioner->get('landlord:app_settings.fetch');
    $beforeShow = $versioner->get('landlord:app_settings.show');
    $beforeOptions = $versioner->get('landlord:app_settings.options');

    $this->actingAs($user)
        ->postJson(route('admin.app_settings.store'), [
            'key' => 'autoplan.min_rest_minutes',
            'type' => 'int',
            'group' => 'autoplan',
            'label' => 'Minimum rest',
            'description' => 'Minutes between shifts',
            'value' => 660,
        ])
        ->assertCreated()
        ->assertJsonPath('data.key', 'autoplan.min_rest_minutes');

    $this->assertDatabaseHas('app_settings', [
        'key' => 'autoplan.min_rest_minutes',
        'type' => 'int',
        'group' => 'autoplan',
    ]);

    expect($versioner->get('landlord:app_settings.fetch'))->toBeGreaterThan($beforeFetch);
    expect($versioner->get('landlord:app_settings.show'))->toBeGreaterThan($beforeShow);
    expect($versioner->get('landlord:app_settings.options'))->toBeGreaterThan($beforeOptions);
});
