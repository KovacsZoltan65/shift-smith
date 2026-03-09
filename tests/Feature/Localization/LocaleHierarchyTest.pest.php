<?php

declare(strict_types=1);

use App\Facades\Settings;
use App\Models\AppSetting;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\CompanySetting;
use App\Models\SettingsMeta;
use App\Models\TenantGroup;
use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    SettingsMeta::query()->updateOrCreate(
        ['key' => 'app.locale'],
        [
            'group' => 'localization',
            'label' => 'Alkalmazás nyelve',
            'type' => 'select',
            'default_value' => 'en',
            'description' => 'Locale setting.',
            'options' => [
                ['label' => 'English', 'value' => 'en'],
                ['label' => 'Magyar', 'value' => 'hu'],
            ],
            'validation' => ['required', 'string', 'in:en,hu'],
            'order_index' => 1,
            'is_editable' => true,
            'is_visible' => true,
        ],
    );

    Route::middleware(['web', 'auth'])->get('/_test/localization/effective-locale', function () {
        return response()->json([
            'locale' => app()->getLocale(),
            'resolved' => Settings::get('app.locale'),
            'source' => Settings::getEffective('app.locale')->source,
        ]);
    });
});

it('uses the app-level locale when no company or user override exists', function (): void {
    $user = User::factory()->create();

    AppSetting::query()->updateOrCreate(
        ['key' => 'app.locale'],
        [
            'value' => 'hu',
            'type' => 'select',
            'group' => 'localization',
            'label' => 'Alkalmazás nyelve',
            'description' => 'App locale.',
        ],
    );

    $this
        ->actingAs($user)
        ->getJson('/_test/localization/effective-locale')
        ->assertOk()
        ->assertJson([
            'locale' => 'hu',
            'resolved' => 'hu',
            'source' => 'app',
        ]);
});

it('uses the company-level locale over the app-level locale', function (): void {
    $tenantGroup = TenantGroup::factory()->create();
    $company = Company::factory()->create(['tenant_group_id' => $tenantGroup->id]);
    $user = User::factory()->create();

    CompanyUser::query()->create([
        'company_id' => $company->id,
        'user_id' => $user->id,
    ]);

    AppSetting::query()->updateOrCreate(
        ['key' => 'app.locale'],
        [
            'value' => 'en',
            'type' => 'select',
            'group' => 'localization',
            'label' => 'Alkalmazás nyelve',
            'description' => 'App locale.',
        ],
    );

    CompanySetting::query()->updateOrCreate(
        ['company_id' => $company->id, 'key' => 'app.locale'],
        [
            'value' => 'hu',
            'type' => 'select',
            'group' => 'localization',
            'label' => 'Alkalmazás nyelve',
            'description' => 'Company locale.',
        ],
    );

    $this
        ->actingAs($user)
        ->withSession([
            'current_company_id' => $company->id,
            'current_tenant_group_id' => $tenantGroup->id,
        ])
        ->getJson('/_test/localization/effective-locale')
        ->assertOk()
        ->assertJson([
            'locale' => 'hu',
            'resolved' => 'hu',
            'source' => 'company',
        ]);
});

it('uses the user-level locale over company and app values', function (): void {
    $tenantGroup = TenantGroup::factory()->create();
    $company = Company::factory()->create(['tenant_group_id' => $tenantGroup->id]);
    $user = User::factory()->create();

    CompanyUser::query()->create([
        'company_id' => $company->id,
        'user_id' => $user->id,
    ]);

    AppSetting::query()->updateOrCreate(
        ['key' => 'app.locale'],
        [
            'value' => 'en',
            'type' => 'select',
            'group' => 'localization',
            'label' => 'Alkalmazás nyelve',
            'description' => 'App locale.',
        ],
    );

    CompanySetting::query()->updateOrCreate(
        ['company_id' => $company->id, 'key' => 'app.locale'],
        [
            'value' => 'hu',
            'type' => 'select',
            'group' => 'localization',
            'label' => 'Alkalmazás nyelve',
            'description' => 'Company locale.',
        ],
    );

    UserSetting::query()->updateOrCreate(
        ['user_id' => $user->id, 'company_id' => $company->id, 'key' => 'app.locale'],
        [
            'value' => 'en',
            'type' => 'select',
            'group' => 'localization',
            'label' => 'Alkalmazás nyelve',
            'description' => 'User locale.',
        ],
    );

    $this
        ->actingAs($user)
        ->withSession([
            'current_company_id' => $company->id,
            'current_tenant_group_id' => $tenantGroup->id,
        ])
        ->getJson('/_test/localization/effective-locale')
        ->assertOk()
        ->assertJson([
            'locale' => 'en',
            'resolved' => 'en',
            'source' => 'user',
        ]);
});

it('applies a global user locale override before company and app values for app.locale', function (): void {
    $tenantGroup = TenantGroup::factory()->create();
    $company = Company::factory()->create(['tenant_group_id' => $tenantGroup->id]);
    $user = User::factory()->create();

    CompanyUser::query()->create([
        'company_id' => $company->id,
        'user_id' => $user->id,
    ]);

    AppSetting::query()->updateOrCreate(
        ['key' => 'app.locale'],
        [
            'value' => 'en',
            'type' => 'select',
            'group' => 'localization',
            'label' => 'Alkalmazás nyelve',
            'description' => 'App locale.',
        ],
    );

    CompanySetting::query()->updateOrCreate(
        ['company_id' => $company->id, 'key' => 'app.locale'],
        [
            'value' => 'hu',
            'type' => 'select',
            'group' => 'localization',
            'label' => 'Alkalmazás nyelve',
            'description' => 'Company locale.',
        ],
    );

    UserSetting::query()->updateOrCreate(
        ['user_id' => $user->id, 'company_id' => null, 'key' => 'app.locale'],
        [
            'value' => 'en',
            'type' => 'select',
            'group' => 'localization',
            'label' => 'Alkalmazás nyelve',
            'description' => 'Global user locale.',
        ],
    );

    $this
        ->actingAs($user)
        ->withSession([
            'current_company_id' => $company->id,
            'current_tenant_group_id' => $tenantGroup->id,
        ])
        ->getJson('/_test/localization/effective-locale')
        ->assertOk()
        ->assertJson([
            'locale' => 'en',
            'resolved' => 'en',
            'source' => 'user',
        ]);
});
