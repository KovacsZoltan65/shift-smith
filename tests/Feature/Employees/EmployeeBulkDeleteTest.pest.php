<?php

declare(strict_types=1);

use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('megtagadja a tömeges törlést, ha a felhasználónak nincs engedélye', function (): void {
    // legyen egy user, akinek nincs se role, se permission
    //$user = $this->createAdminUser(); // ha csak ez van a trait-ben, ok
    $user = $this->createAdminUser();
    $user->syncRoles([]);
    $user->syncPermissions([]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $employees = Employee::factory()->count(2)->create();

    $this
        ->actingAs($user)
        ->deleteJson('/employees/destroy_bulk', ['ids' => $employees->pluck('id')->all()])
        ->assertForbidden();
});

it('lehetővé teszi az adminisztrátor számára a dolgozók tömeges törlését (soft törlés) és a gyorsítótár verzióinak felborítását', function (): void {
    // admin user: kapjon role-t / permissiont (a seed alapján)
    $user = $this->createAdminUser();

    // ha a createAdminUser nem ad role-t automatikusan, akkor add rá:
    // $user->syncRoles(['admin']);

    // ha permissiont kell explicit:
    // $user->givePermissionTo('employees.deleteAny');

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $employees = Employee::factory()->count(3)->create();
    $ids = $employees->pluck('id')->all();

    $this
        ->actingAs($user)
        ->deleteJson('/employees/destroy_bulk', ['ids' => $ids])
        ->assertOk();

    foreach ($ids as $id) {
        $this->assertSoftDeleted('employees', ['id' => $id]);
    }
});



/*
it('tömeges alkalmazotttörlések', function (): void {
    $user = $this->createAdminUser();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $company = Company::factory()->create();
    $e1 = Employee::factory()->create(['company_id' => $company->id]);
    $e2 = Employee::factory()->create(['company_id' => $company->id]);

    // a célállapot szerint: DELETE /employees/destroy_bulk
    $this->actingAs($user)
        ->deleteJson(route('employees.destroy_bulk'), [
            'ids' => [$e1->id, $e2->id],
        ])
        ->assertOk()
        ->assertJsonPath('deleted', 2);

    $this->assertSoftDeleted('employees', ['id' => $e1->id]);
    $this->assertSoftDeleted('employees', ['id' => $e2->id]);
});
*/