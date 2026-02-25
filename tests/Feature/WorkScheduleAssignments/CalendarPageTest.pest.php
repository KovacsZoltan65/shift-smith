<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\User;
use App\Models\WorkSchedule;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('calendar oldal planner jogot ad adminnak', function (): void {
    [, $company] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($company);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    WorkSchedule::factory()->create([
        'company_id' => $company->id,
        'status' => 'draft',
    ]);

    $this->actingAsUserInCompany($user, $company)
        ->get(route('scheduling.calendar'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Scheduling/Calendar/Index')
            ->where('permissions.viewer', true)
            ->where('permissions.planner', true)
        );
});

it('calendar oldal planner jogot ad superadminnak', function (): void {
    [, $company] = $this->createTenantWithCompany();
    $user = $this->createSuperadminUser();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    WorkSchedule::factory()->create([
        'company_id' => $company->id,
        'status' => 'draft',
    ]);

    $this->actingAsUserInCompany($user, $company)
        ->get(route('scheduling.calendar'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Scheduling/Calendar/Index')
            ->where('permissions.viewer', true)
            ->where('permissions.planner', true)
        );
});

it('calendar oldal planner jogot megtagad egyszeru usertol', function (): void {
    [, $company] = $this->createTenantWithCompany();
    /** @var User $user */
    $user = User::factory()->create();
    $user->assignRole('user');
    $user->companies()->syncWithoutDetaching([$company->id]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    WorkSchedule::factory()->create([
        'company_id' => $company->id,
        'status' => 'draft',
    ]);

    $this->actingAsUserInCompany($user, $company)
        ->get(route('scheduling.calendar'))
        ->assertForbidden();
});
