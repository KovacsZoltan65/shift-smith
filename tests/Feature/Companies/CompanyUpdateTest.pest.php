<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\User;
use App\Services\Cache\CacheVersionService;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('denies company update if user lacks permission', function (): void {
    /** @var User $user */
    $user = $this->createAdminUser();
    $user->assignRole('user');

    /** @var Company $company */
    $company = Company::factory()->create();

    $this
        ->actingAs($user)
        ->putJson(route('companies.update', ['id' => $company->id]), [
            'name' => 'New',
        ])
        ->assertForbidden();
});

it('validates required fields on update', function (): void {
    $user = $this->createAdminUser();
    $company = Company::factory()->create();

    $this
        ->actingAs($user)
        ->putJson(route('companies.update', ['id' => $company->id]), [
            'name' => '',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('allows admin to update a company and bumps cache versions', function (): void {
    $user = $this->createAdminUser();

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $versioner = app(CacheVersionService::class);

    /** @var Company $company */
    $company = Company::factory()->create([
        'name' => 'Old Name',
        'active' => true,
    ]);

    Cache::forever('v:companies.fetch', 1);
    Cache::forever('v:selectors.companies', 1);

    // Biztosan "elfogadott" mezők + email formátum: factory
    $payload = Company::factory()->make([
        'name' => 'New Name',
        'active' => false,
    ])->only(['name', 'email', 'address', 'phone', 'active']);

    $this
        ->actingAs($user)
        ->putJson(route('companies.update', ['id' => $company->id]), $payload)
        ->assertOk();

    $this->assertDatabaseHas('companies', [
        'id' => $company->id,
        'name' => 'New Name',
        'email' => $payload['email'],
        'active' => 0,
    ]);

    expect($versioner->get('companies.fetch'))->toBe(2);
    expect($versioner->get('selectors.companies'))->toBe(2);
});

it('allows keeping the same email on update (unique ignore current id)', function (): void {
    $user = $this->createAdminUser();

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    // Legyen "valid" email a te szabályaid szerint:
    $email = Company::factory()->make()->email;

    /** @var Company $company */
    $company = Company::factory()->create(['email' => $email]);

    // Update payload: tartsuk meg ugyanazt az emailt
    $payload = Company::factory()->make([
        'name' => 'Same Email Updated',
        'email' => $email,
    ])->only(['name', 'email', 'address', 'phone', 'active']);

    $this
        ->actingAs($user)
        ->putJson(route('companies.update', ['id' => $company->id]), $payload)
        ->assertOk();
});
