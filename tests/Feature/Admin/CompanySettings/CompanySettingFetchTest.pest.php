<?php

declare(strict_types=1);

use App\Models\AppSetting;
use App\Models\CompanySetting;
use App\Models\User;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('megtagadja a fetch-et jogosultság nélkül', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->companies()->syncWithoutDetaching([$company->id]);
    $user->assignRole('user');

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $this->actingAsUserInCompany($user, $company)
        ->getJson(route('admin.company_settings.fetch'))
        ->assertForbidden();
});

it('csak a kiválasztott company rekordjait listázza és effective source-ot ad', function (): void {
    [$tenantA, $companyA] = $this->createTenantWithCompany();
    [$tenantB, $companyB] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($companyA);

    AppSetting::query()->create([
        'key' => 'leave.shared',
        'value' => 1,
        'type' => 'int',
        'group' => 'leave',
    ]);

    CompanySetting::query()->create([
        'company_id' => $companyA->id,
        'key' => 'leave.shared',
        'value' => 2,
        'type' => 'int',
        'group' => 'leave',
    ]);

    CompanySetting::query()->create([
        'company_id' => $companyB->id,
        'key' => 'leave.other',
        'value' => 3,
        'type' => 'int',
        'group' => 'leave',
    ]);

    $response = $this->actingAsUserInCompany($user, $companyA)
        ->getJson(route('admin.company_settings.fetch', ['sortBy' => 'key', 'sortDir' => 'asc']));

    $response->assertOk()->assertJsonStructure([
        'items',
        'meta' => ['current_page', 'per_page', 'total', 'last_page'],
        'filter',
        'options',
    ]);

    expect($response->json('meta.total'))->toBe(1);
    expect($response->json('items.0.key'))->toBe('leave.shared');
    expect($response->json('items.0.source'))->toBe('company');
});
