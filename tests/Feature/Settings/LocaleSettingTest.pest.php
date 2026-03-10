<?php

declare(strict_types=1);

use App\Models\AppSetting;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\SettingsMeta;
use App\Models\UserSetting;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();

    SettingsMeta::query()->updateOrCreate(
        ['key' => 'app.locale'],
        [
            'group' => 'localization',
            'label' => 'Alkalmazás nyelve',
            'type' => 'select',
            'default_value' => 'hu',
            'options' => [
                ['label' => 'English', 'value' => 'en'],
                ['label' => 'Magyar', 'value' => 'hu'],
            ],
            'validation' => ['required', 'in:en,hu'],
            'order_index' => 1,
            'is_editable' => true,
            'is_visible' => true,
        ]
    );
});

it('app szintu locale az alapertelmezett a requestben', function (): void {
    $company = Company::factory()->create();
    $user = $this->createAdminUser($company);

    AppSetting::query()->updateOrCreate(['key' => 'app.locale'], ['value' => 'en']);

    $this->actingAsUserInCompany($user, $company)
        ->get(route('companies.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Companies/Index')
            ->where('title', 'Companies')
            ->where('locale', 'en')
            ->where('preferences.locale', 'en')
        );
});

it('seedelt json string app.locale erteket is helyesen alkalmazza', function (): void {
    $company = Company::factory()->create();
    $user = $this->createAdminUser($company);

    DB::table('app_settings')->updateOrInsert(
        ['key' => 'app.locale'],
        [
            'value' => json_encode('en', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'type' => 'string',
            'group' => 'localization',
            'label' => 'Alkalmazás nyelve',
            'description' => 'Az alkalmazás alapértelmezett nyelve.',
            'created_at' => now(),
            'updated_at' => now(),
        ]
    );

    $this->actingAsUserInCompany($user, $company)
        ->get(route('companies.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Companies/Index')
            ->where('title', 'Companies')
            ->where('locale', 'en')
            ->where('preferences.locale', 'en')
        );
});

it('company locale felulirja az app locale-t', function (): void {
    $company = Company::factory()->create();
    $user = $this->createAdminUser($company);

    AppSetting::query()->updateOrCreate(['key' => 'app.locale'], ['value' => 'hu']);
    CompanySetting::query()->updateOrCreate(
        ['company_id' => $company->id, 'key' => 'app.locale'],
        ['value' => 'en']
    );

    $this->actingAsUserInCompany($user, $company)
        ->get(route('companies.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Companies/Index')
            ->where('title', 'Companies')
            ->where('locale', 'en')
        );
});

it('user locale felulirja a company locale-t', function (): void {
    $company = Company::factory()->create();
    $user = $this->createAdminUser($company);

    AppSetting::query()->updateOrCreate(['key' => 'app.locale'], ['value' => 'hu']);
    CompanySetting::query()->updateOrCreate(
        ['company_id' => $company->id, 'key' => 'app.locale'],
        ['value' => 'hu']
    );
    UserSetting::query()->updateOrCreate(
        ['user_id' => $user->id, 'company_id' => null, 'key' => 'app.locale'],
        ['value' => 'en']
    );

    $this->actingAsUserInCompany($user, $company)
        ->get(route('companies.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Companies/Index')
            ->where('title', 'Companies')
            ->where('locale', 'en')
        );
});

it('ervenytelen locale erteket elutasit a preference route', function (): void {
    $company = Company::factory()->create();
    $user = $this->createAdminUser($company);

    $this->actingAsUserInCompany($user, $company)
        ->postJson(route('preferences.locale'), ['locale' => 'de'])
        ->assertStatus(422);

    $this->assertDatabaseMissing('user_settings', [
        'user_id' => $user->id,
        'key' => 'app.locale',
    ]);
});

it('a header locale valtas global user override-kent mentodik es a kovetkezo requesten ervenyesul', function (): void {
    $company = Company::factory()->create();
    $user = $this->createAdminUser($company);

    AppSetting::query()->updateOrCreate(['key' => 'app.locale'], ['value' => 'hu']);

    $this->actingAsUserInCompany($user, $company)
        ->postJson(route('preferences.locale'), ['locale' => 'en'])
        ->assertNoContent();

    $this->assertDatabaseHas('user_settings', [
        'user_id' => $user->id,
        'company_id' => null,
        'key' => 'app.locale',
        'value' => 'en',
    ]);

    $this->actingAsUserInCompany($user, $company)
        ->get(route('companies.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Companies/Index')
            ->where('title', 'Companies')
            ->where('locale', 'en')
            ->where('preferences.locale', 'en')
        );
});
