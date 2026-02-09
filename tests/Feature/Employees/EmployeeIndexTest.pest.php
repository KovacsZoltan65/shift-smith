<?php

declare(strict_types=1);

use App\Models\User;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('denies employees index if user lacks permission', function (): void {
    /** @var User $user */
    $user = User::factory()->create();
    $user->assignRole('user'); // csak view/viewAny joga van a seeder szerint

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $this->actingAs($user)
        ->get(route('employees.index'))
        ->assertForbidden();
});

it('allows admin to open employees index', function (): void {
    $user = $this->createAdminUser();

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $this->actingAs($user)
        ->get(route('employees.index'))
        ->assertOk();
});
