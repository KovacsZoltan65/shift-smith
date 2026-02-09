<?php

declare(strict_types=1);

use App\Models\Company;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('denies company delete if user lacks permission', function (): void {
    
    $user = $this->createAdminUser(); // <- ha nálad ez létezik a CreatesUsers trait-ben
    $user->syncRoles([]);
    $user->syncPermissions([]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user = $user->refresh();

    $company = Company::factory()->create();

    $this
        ->actingAs($user)
        ->deleteJson(route('companies.destroy', ['id' => $company->id]))
        ->assertForbidden();
});

it('allows admin to delete a company (soft delete) and bumps cache versions', function (): void {

    $user = $this->createAdminUser(); // <- ha nálad ez létezik a CreatesUsers trait-ben
    $user->syncRoles([]);
    $user->syncPermissions([]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user = $user->refresh();

    /** @var Company $company */
    $company = Company::factory()->create();

    Cache::forever('v:companies.fetch', 1);
    Cache::forever('v:selectors.companies', 1);

    $this
        ->actingAs($user)
        ->deleteJson(route('companies.destroy', ['id' => $company->id]))
        ->assertOk()
        ->assertJson(true);

    $this->assertSoftDeleted('companies', ['id' => $company->id]);

    expect($versioner->get('companies.fetch'))->toBe(2);
    expect($versioner->get('selectors.companies'))->toBe(2);
});
