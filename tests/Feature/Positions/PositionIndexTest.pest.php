<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\User;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('denies positions index if user lacks permission', function (): void {
    $company = Company::factory()->create();

    /** @var User $user */
    $user = User::factory()->create();
    $user->assignRole('user');

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $this->actingAs($user)
        ->get(route('positions.index', ['company_id' => $company->id]))
        ->assertForbidden();
});

it('allows admin to open positions index', function (): void {
    $company = Company::factory()->create();
    $user = $this->createSuperadminUser();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $this->actingAs($user)
        ->withSession(['current_company_id' => $company->id])
        ->get(route('positions.index'))
        ->assertOk();
});
