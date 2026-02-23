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
    $user = $this->createAdminUser();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $company = Company::factory()->create();
    WorkSchedule::factory()->create([
        'company_id' => $company->id,
        'status' => 'draft',
    ]);

    $this->actingAs($user)
        ->withSession(['current_company_id' => $company->id])
        ->get(route('scheduling.calendar'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Scheduling/Calendar/Index')
            ->where('permissions.viewer', true)
            ->where('permissions.planner', true)
        );
});

it('calendar oldal planner jogot ad superadminnak', function (): void {
    $user = $this->createSuperadminUser();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $company = Company::factory()->create();
    WorkSchedule::factory()->create([
        'company_id' => $company->id,
        'status' => 'draft',
    ]);

    $this->actingAs($user)
        ->withSession(['current_company_id' => $company->id])
        ->get(route('scheduling.calendar'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Scheduling/Calendar/Index')
            ->where('permissions.viewer', true)
            ->where('permissions.planner', true)
        );
});

it('calendar oldal planner jogot megtagad egyszeru usertol', function (): void {
    /** @var User $user */
    $user = User::factory()->create();
    $user->assignRole('user');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $company = Company::factory()->create();
    WorkSchedule::factory()->create([
        'company_id' => $company->id,
        'status' => 'draft',
    ]);

    $this->actingAs($user)
        ->withSession(['current_company_id' => $company->id])
        ->get(route('scheduling.calendar'))
        ->assertForbidden();
});
