<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\SettingsMeta;
use App\Services\Cache\CacheVersionService;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('version bump történik settings mentésnél', function (): void {
    $user = $this->createSuperadminUser();
    $company = Company::factory()->create();

    SettingsMeta::query()->create([
        'key' => 'test.settings.bump',
        'group' => 'test',
        'label' => 'Bump',
        'type' => 'int',
        'default_value' => 1,
        'validation' => ['required', 'integer'],
        'order_index' => 1,
        'is_editable' => true,
        'is_visible' => true,
    ]);

    $versioner = app(CacheVersionService::class);
    $before = $versioner->get("settings.company.{$company->id}");

    $this->actingAs($user)
        ->withSession(['current_company_id' => $company->id])
        ->postJson(route('settings.save'), [
            'level' => 'company',
            'company_id' => $company->id,
            'values' => [
                ['key' => 'test.settings.bump', 'value' => 9],
            ],
        ])
        ->assertOk();

    $after = $versioner->get("settings.company.{$company->id}");

    expect($after)->toBeGreaterThan($before);
});

