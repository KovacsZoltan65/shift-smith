<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\User;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('redirects to company selector when company is missing from session on work schedule assignment routes', function (): void {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    /** @var User $user */
    $user = $this->createAdminUser($companyA);
    $user->companies()->syncWithoutDetaching([$companyB->id]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $this->actingAs($user)
        ->get(route('scheduling.calendar'))
        ->assertRedirect(route('company.select'));
});
