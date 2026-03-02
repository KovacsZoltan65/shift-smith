<?php

declare(strict_types=1);

use App\Models\CompanySetting;
use App\Services\Cache\CacheVersionService;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('validálja a company scoped unique kulcsot', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($company);

    CompanySetting::query()->create([
        'company_id' => $company->id,
        'key' => 'leave.max_days',
        'value' => 1,
        'type' => 'int',
        'group' => 'leave',
    ]);

    $this->actingAsUserInCompany($user, $company)
        ->postJson(route('admin.company_settings.store'), [
            'key' => 'leave.max_days',
            'type' => 'int',
            'group' => 'leave',
            'value' => 5,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['key']);
});

it('ment a current company scope-ba és bumpolja a cache verziókat', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($company);
    $versioner = app(CacheVersionService::class);
    $beforeFetch = $versioner->get("company_settings:{$company->id}:fetch");
    $beforeEffective = $versioner->get("effective_settings:{$company->id}:all");

    $this->actingAsUserInCompany($user, $company)
        ->postJson(route('admin.company_settings.store'), [
            'key' => 'leave.max_days',
            'type' => 'int',
            'group' => 'leave',
            'value' => 12,
        ])
        ->assertCreated()
        ->assertJsonPath('data.company_id', $company->id);

    $this->assertDatabaseHas('company_settings', [
        'company_id' => $company->id,
        'key' => 'leave.max_days',
    ]);

    expect($versioner->get("company_settings:{$company->id}:fetch"))->toBeGreaterThan($beforeFetch);
    expect($versioner->get("effective_settings:{$company->id}:all"))->toBeGreaterThan($beforeEffective);
});
