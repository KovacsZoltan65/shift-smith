<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CreatesUsers;

uses(CreatesUsers::class);

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('redirects guests to login on companies index', function (): void {
    $this->get(route('companies.index'))
        ->assertRedirect();
});

it('denies companies index if user lacks viewAny permission', function (): void {
    /** @var User $user */
    $user = User::factory()->create();
    $user->assignRole('user');

    $this->actingAs($user)
        ->get(route('companies.index'))
        ->assertForbidden();
});

it('renders Companies/Index for admin (Inertia) and passes filter defaults', function (): void {
    $user = $this->createAdminUser();

    $this->actingAs($user)
        ->get(route('companies.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Companies/Index')
            ->where('title', 'Cégek')
            ->has('filter', fn (Assert $filter) => $filter
                ->where('search', null)
                ->where('field', 'id')
                ->where('order', 'desc')
                ->where('page', 1)
                ->where('per_page', 10)
            )
        );
});
