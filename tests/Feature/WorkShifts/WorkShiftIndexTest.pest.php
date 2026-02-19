<?php

declare(strict_types=1);

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('átirányítja a vendégeket a bejelentkezéshez a műszakok indexén', function (): void {
    $this->get(route('work_shifts.index'))
        ->assertRedirect();
});

it('megtagadja a műszakok indexelését, ha nincs viewAny jogosultság', function (): void {
    /** @var User $user */
    $user = User::factory()->create();
    $user->assignRole('user');

    $this->actingAs($user)
        ->get(route('work_shifts.index'))
        ->assertForbidden();
});

it('megjeleníti a WorkShifts/Index oldalt alapértelmezett szűrőkkel adminnak', function (): void {
    $user = $this->createAdminUser();

    $this->actingAs($user)
        ->get(route('work_shifts.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('WorkShifts/Index')
            ->where('title', 'Műszakok')
            ->has('filter', fn (Assert $filter) => $filter
                ->where('search', null)
                ->where('field', 'id')
                ->where('order', 'desc')
                ->where('page', 1)
                ->where('per_page', 10)
            )
        );
});
