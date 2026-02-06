<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\User;
use App\Services\Cache\CacheVersionService;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\CreatesUsers;

uses(CreatesUsers::class);

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('denies company creation if user lacks permission', function (): void {
    /** @var User $user */
    $user = User::factory()->create();
    $user->assignRole('user');

    $this
        ->actingAs($user)
        ->postJson(route('companies.store'), ['name' => 'Nope'])
        ->assertForbidden();

    $this->assertDatabaseMissing('companies', ['name' => 'Nope']);
});

it('validates required fields on store', function (): void {
    $user = $this->createAdminUser();

    $this
        ->actingAs($user)
        ->postJson(route('companies.store'), ['name' => ''])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('allows admin to store a company and bumps cache versions', function (): void {
    $user = $this->createAdminUser();

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $versioner = app(CacheVersionService::class);

    Cache::forever('v:companies.fetch', 1);
    Cache::forever('v:selectors.companies', 1);

    // Factory által generált "biztosan elfogadott" formátum
    $payload = Company::factory()->make([
        'name' => 'Test Company Kft.',
        'active' => true,
    ])->only(['name', 'email', 'address', 'phone', 'active']);

    $this
        ->actingAs($user)
        ->postJson(route('companies.store'), $payload)
        ->assertOk();

    $this->assertDatabaseHas('companies', [
        'name' => 'Test Company Kft.',
        'email' => $payload['email'],
        'active' => 1,
    ]);

    expect($versioner->get('companies.fetch'))->toBe(2);
    expect($versioner->get('selectors.companies'))->toBe(2);
});

it('enforces unique email among not-soft-deleted companies', function (): void {
    $user = $this->createAdminUser();

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $email = Company::factory()->make()->email;

    Company::factory()->create(['email' => $email]);

    $payloadDup = Company::factory()->make([
        'name' => 'Another',
        'email' => $email,
    ])->only(['name', 'email', 'address', 'phone', 'active']);

    $this
        ->actingAs($user)
        ->postJson(route('companies.store'), $payloadDup)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);

    // Soft delete után engedje
    $old = Company::query()->where('email', $email)->firstOrFail();
    $old->delete();

    $payloadAfterDelete = Company::factory()->make([
        'name' => 'Allowed After Delete',
        'email' => $email,
    ])->only(['name', 'email', 'address', 'phone', 'active']);

    $this
        ->actingAs($user)
        ->postJson(route('companies.store'), $payloadAfterDelete)
        ->assertOk();
});
