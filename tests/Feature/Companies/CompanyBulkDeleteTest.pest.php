<?php

use App\Models\Company;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    //$this->seedRolesAndPermissions();
    $this->seedRolesAndPermissions();
});

it('megtagadja a tömeges törlést, ha a felhasználónak nincs engedélye', function (): void {
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

it('lehetővé teszi az adminisztrátor számára a vállalatok tömeges törlését (soft törlés) és a gyorsítótár verzióinak felborítását', function (): void {
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
