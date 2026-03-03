<?php

declare(strict_types=1);

use App\Models\Company;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('redirects guests to login on work shifts index', function (): void {
    $this->get(route('work_shifts.index'))->assertRedirect();
});

it('forbids index when user lacks view permission', function (): void {
    $company = Company::factory()->create();
    $user = $this->createAdminUser($company);
    $user->syncRoles([]);
    $user->syncPermissions([]);

    $this->actingAs($user)
        ->withSession([
            'current_company_id' => $company->id,
            'current_tenant_group_id' => $company->tenant_group_id,
        ])
        ->get(route('work_shifts.index'))
        ->assertRedirect();
});

it('renders WorkShifts index with scoped filter', function (): void {
    $company = Company::factory()->create();
    $user = $this->createAdminUser($company);

    $this->actingAs($user)
        ->withSession([
            'current_company_id' => $company->id,
            'current_tenant_group_id' => $company->tenant_group_id,
        ])
        ->get(route('work_shifts.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('WorkShifts/Index')
            ->where('title', 'Műszakok')
            ->has('filter', fn (Assert $filter) => $filter
                ->where('search', null)
                ->where('company_id', $company->id)
                ->where('field', 'id')
                ->where('order', 'desc')
                ->where('page', 1)
                ->where('per_page', 10)
            )
        );
});
