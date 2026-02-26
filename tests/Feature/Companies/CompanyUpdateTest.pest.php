<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\User;
use App\Services\Cache\CacheVersionService;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('denies company update if user lacks permission', function (): void {
    /** @var User $user */
    $user = $this->createAdminUser();
    $user->syncRoles([]);
    $user->syncPermissions([]);

    /** @var Company $company */
    $company = Company::factory()->create();

    $payload = Company::factory()->make([
        'name' => 'New',
        'active' => true,
    ])->only(['name', 'email', 'address', 'phone', 'active']);

    $this
        ->actingAs($user)
        ->putJson(route('companies.update', ['id' => $company->id]), $payload)
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
    $companiesFetchBefore = $versioner->get('companies.fetch');
    $companiesSelectorBefore = $versioner->get('selectors.companies');

    /** @var Company $company */
    $company = Company::factory()->create([
        'name' => 'Old Name',
        'active' => true,
    ]);

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

    expect($versioner->get('companies.fetch'))->toBeGreaterThan($companiesFetchBefore);
    expect($versioner->get('selectors.companies'))->toBeGreaterThan($companiesSelectorBefore);
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
        'active' => true,
    ])->only(['name', 'email', 'address', 'phone', 'active']);

    $this
        ->actingAs($user)
        ->putJson(route('companies.update', ['id' => $company->id]), $payload)
        ->assertOk();
});
