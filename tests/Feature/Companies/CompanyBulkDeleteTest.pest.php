<?php

use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\CreatesUsers;

uses(
    RefreshDatabase::class,
    CreatesUsers::class
);

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('denies bulk delete if user lacks permission', function (): void {
    // legyen egy user, akinek nincs se role, se permission
    $user = $this->createAdminUser(); // ha csak ez van a trait-ben, ok
    $user->syncRoles([]);
    $user->syncPermissions([]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $companies = Company::factory()->count(2)->create();

    $this
        ->actingAs($user)
        ->deleteJson('/companies/destroy_bulk', ['ids' => $companies->pluck('id')->all()])
        ->assertForbidden();
});

it('allows admin to bulk delete companies (soft delete) and bumps cache versions', function (): void {
    // admin user: kapjon role-t / permissiont (a seed alapján)
    $user = $this->createAdminUser();

    // ha a createAdminUser nem ad role-t automatikusan, akkor add rá:
    // $user->syncRoles(['admin']);

    // ha permissiont kell explicit:
    // $user->givePermissionTo('companies.deleteAny');

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $companies = Company::factory()->count(3)->create();
    $ids = $companies->pluck('id')->all();

    $this
        ->actingAs($user)
        ->deleteJson('/companies/destroy_bulk', ['ids' => $ids])
        ->assertOk();

    foreach ($ids as $id) {
        $this->assertSoftDeleted('companies', ['id' => $id]);
    }
});
