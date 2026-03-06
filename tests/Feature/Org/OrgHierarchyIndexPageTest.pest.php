<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('returns 403 on hierarchy page without permission', function (): void {
    $company = Company::factory()->create();
    /** @var User $user */
    $user = User::factory()->create();
    $user->assignRole('user');
    $user->companies()->syncWithoutDetaching([$company->id]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $this->actingAsUserInCompany($user, $company)
        ->get(route('org.hierarchy.index'))
        ->assertForbidden();
});

it('returns 200 on hierarchy page with permission', function (): void {
    $company = Company::factory()->create();
    $user = $this->createAdminUser($company);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $this->actingAsUserInCompany($user, $company)
        ->get(route('org.hierarchy.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('HR/Hierarchy/Index')
            ->where('title', 'Szervezeti hierarchia')
        );
});
