<?php

declare(strict_types=1);

use App\Models\AppSetting;
use App\Models\CompanySetting;
use App\Models\User;
use App\Models\UserSetting;
use App\Services\Cache\CacheVersionService;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('user company scoped override felülírja a company és app értéket', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $actor = $this->createAdminUser($company);
    $target = User::factory()->create();

    AppSetting::query()->create([
        'key' => 'leave.priority',
        'value' => 'app',
        'type' => 'string',
        'group' => 'leave',
    ]);
    CompanySetting::query()->create([
        'company_id' => $company->id,
        'key' => 'leave.priority',
        'value' => 'company',
        'type' => 'string',
        'group' => 'leave',
    ]);
    UserSetting::query()->create([
        'user_id' => $target->id,
        'company_id' => $company->id,
        'key' => 'leave.priority',
        'value' => 'user',
    ]);

    $response = $this->actingAsUserInCompany($actor, $company)
        ->getJson(route('admin.company_settings.effective', [
            'keys' => ['leave.priority'],
            'user_id' => $target->id,
        ]));

    $response->assertOk();
    expect($response->json('data.0.source'))->toBe('user');
    expect($response->json('data.0.effective_value'))->toBe('user');
});

it('company felülírja az appot user override nélkül', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $actor = $this->createAdminUser($company);

    AppSetting::query()->create([
        'key' => 'leave.company_wins',
        'value' => 1,
        'type' => 'int',
        'group' => 'leave',
    ]);
    CompanySetting::query()->create([
        'company_id' => $company->id,
        'key' => 'leave.company_wins',
        'value' => 5,
        'type' => 'int',
        'group' => 'leave',
    ]);

    $response = $this->actingAsUserInCompany($actor, $company)
        ->getJson(route('admin.company_settings.effective', [
            'keys' => ['leave.company_wins'],
        ]));

    expect($response->json('data.0.source'))->toBe('company');
    expect($response->json('data.0.effective_value'))->toBe(5);
});

it('legacy user fallback csak flag mellett él', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $actor = $this->createAdminUser($company);
    $target = User::factory()->create();

    AppSetting::query()->create([
        'key' => 'settings.user_legacy_global_override_enabled',
        'value' => false,
        'type' => 'bool',
        'group' => 'settings',
    ]);
    AppSetting::query()->create([
        'key' => 'leave.legacy',
        'value' => 'app',
        'type' => 'string',
        'group' => 'leave',
    ]);
    CompanySetting::query()->create([
        'company_id' => $company->id,
        'key' => 'leave.legacy',
        'value' => 'company',
        'type' => 'string',
        'group' => 'leave',
    ]);
    UserSetting::query()->create([
        'user_id' => $target->id,
        'company_id' => null,
        'key' => 'leave.legacy',
        'value' => 'legacy-user',
    ]);

    $disabled = $this->actingAsUserInCompany($actor, $company)
        ->getJson(route('admin.company_settings.effective', [
            'keys' => ['leave.legacy'],
            'user_id' => $target->id,
        ]))
        ->assertOk();

    expect($disabled->json('data.0.source'))->toBe('company');

    $flag = AppSetting::query()->where('key', 'settings.user_legacy_global_override_enabled')->firstOrFail();
    $flag->value = true;
    $flag->save();
    app(CacheVersionService::class)->bump('landlord:app_settings.show');
    app(CacheVersionService::class)->bump("effective_settings:{$company->id}:all");

    $enabled = $this->actingAsUserInCompany($actor, $company)
        ->getJson(route('admin.company_settings.effective', [
            'keys' => ['leave.legacy'],
            'user_id' => $target->id,
        ]))
        ->assertOk();

    expect($enabled->json('data.0.source'))->toBe('user_legacy');
    expect($enabled->json('data.0.effective_value'))->toBe('legacy-user');
});

it('tenant izoláció és company scope érvényes az effective endpointnál', function (): void {
    [$tenantA, $companyA] = $this->createTenantWithCompany();
    [$tenantB, $companyB] = $this->createTenantWithCompany();
    $actor = $this->createAdminUser($companyA);

    CompanySetting::query()->create([
        'company_id' => $companyB->id,
        'key' => 'leave.foreign_only',
        'value' => 99,
        'type' => 'int',
        'group' => 'leave',
    ]);

    $response = $this->actingAsUserInCompany($actor, $companyA)
        ->getJson(route('admin.company_settings.effective', [
            'keys' => ['leave.foreign_only'],
        ]))
        ->assertOk();

    expect($response->json('data.0.source'))->toBe('none');
    expect($response->json('data.0.effective_value'))->toBeNull();
});
