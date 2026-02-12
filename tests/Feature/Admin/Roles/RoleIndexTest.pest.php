<?php

declare(strict_types=1);

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('átirányítja a vendégeket a bejelentkezéshez az szerepek indexén', function (): void {
    $this->get(route('admin.roles.index'))
        ->assertRedirect();
});

it('megtagadja az szerepek indexelését, ha a felhasználónak nincs viewAny jogosultsága', function (): void {
    /** @var User $user */
    $user = User::factory()->create();
    $user->assignRole('user');

    $this->actingAs($user)
        ->get(route('admin.roles.index'))
        ->assertForbidden();
});

it('Az Admin/Roles/Index ablakot jeleníti meg az adminisztrátor számára (tehetetlenség), és átadja a szűrő alapértelmezett értékeit.', function (): void {
    $user = $this->createAdminUser();

    $this->actingAs($user)
        ->get(route('admin.roles.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Roles/Index')
            ->where('title', 'Szerepkörök')
            ->has('filter', fn (Assert $filter) => $filter
                ->where('search', null)
                ->where('field', 'id')
                ->where('order', 'desc')
                ->where('page', 1)
                ->where('per_page', 10)
            )
        );
});
