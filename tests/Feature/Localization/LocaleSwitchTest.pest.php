<?php

declare(strict_types=1);

use App\Models\SettingsMeta;
use App\Models\UserSetting;
use App\Models\User;

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
});

it('persists the selected locale as a user setting override', function (): void {
    /** @var User $user */
    $user = User::factory()->create();

    $this
        ->actingAs($user)
        ->from(route('profile.edit'))
        ->post(route('locale.update'), ['locale' => 'hu'])
        ->assertRedirect(route('profile.edit'));

    $this->assertDatabaseHas('user_settings', [
        'user_id' => $user->id,
        'company_id' => null,
        'key' => 'app.locale',
    ]);
});

it('rejects unsupported locale values', function (): void {
    /** @var User $user */
    $user = User::factory()->create();

    $this
        ->actingAs($user)
        ->from(route('profile.edit'))
        ->post(route('locale.update'), ['locale' => 'de'])
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHasErrors('locale');
});

it('shares the active locale from the resolved settings chain with inertia responses', function (): void {
    /** @var User $user */
    $user = User::factory()->create();

    $this
        ->actingAs($user)
        ->from(route('profile.edit'))
        ->post(route('locale.update'), ['locale' => 'hu']);

    $this
        ->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('locale', 'hu'));
});

it('falls back to the configured fallback locale when a stored locale is invalid', function (): void {
    /** @var User $user */
    $user = User::factory()->create();

    UserSetting::query()->create([
        'user_id' => $user->id,
        'company_id' => null,
        'key' => 'app.locale',
        'value' => 'de',
        'type' => 'select',
        'group' => 'localization',
        'label' => 'Alkalmazás nyelve',
        'description' => 'Invalid locale.',
    ]);

    $this
        ->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('locale', config('app.fallback_locale', 'en')));
});
