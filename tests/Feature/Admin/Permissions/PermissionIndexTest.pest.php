<?php

declare(strict_types=1);

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('átirányítja a vendégeket a bejelentkezéshez az engedélyek indexén', function (): void {
    $this->get(route('admin.permissions.index'))
        ->assertRedirect();
});

it('megtagadja az engedélyek indexelését, ha a felhasználónak nincs viewAny jogosultsága', function (): void {
    /** @var User $user */
    $user = User::factory()->create();
    $user->assignRole('user');

    $this->actingAs($user)
        ->get(route('admin.permissions.index'))
        ->assertForbidden();
});

it('Az Admin/Permissions/Index ablakot jeleníti meg az adminisztrátor számára (tehetetlenség), és átadja a szűrő alapértelmezett értékeit.', function (): void {
    $user = $this->createAdminUser();

    $this->actingAs($user)
        ->get(route('admin.permissions.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Permissions/Index')
            ->where('title', 'Jogosultságok')
            ->has('filter', fn (Assert $filter) => $filter
                ->where('search', null)
                ->where('field', 'id')
                ->where('order', 'desc')
                ->where('page', 1)
                ->where('per_page', 10)
            )
        );
});
