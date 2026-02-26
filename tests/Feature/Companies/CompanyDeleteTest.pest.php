<?php

declare(strict_types=1);

use App\Models\Company;
use App\Services\Cache\CacheVersionService;
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

    $user = $this->createAdminUser();

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user = $user->refresh();

    $versioner = app(CacheVersionService::class);
    $companiesFetchBefore = $versioner->get('companies.fetch');
    $companiesSelectorBefore = $versioner->get('selectors.companies');

    /** @var Company $company */
    $company = Company::factory()->create();

    $this
        ->actingAs($user)
        ->deleteJson(route('companies.destroy', ['id' => $company->id]))
        ->assertOk()
        ->assertJson(['deleted' => true]);

    $this->assertSoftDeleted('companies', ['id' => $company->id]);

    expect($versioner->get('companies.fetch'))->toBeGreaterThan($companiesFetchBefore);
    expect($versioner->get('selectors.companies'))->toBeGreaterThan($companiesSelectorBefore);
});
