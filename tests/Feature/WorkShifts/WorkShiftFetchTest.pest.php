<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\WorkShift;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('redirects guests on work shift fetch', function (): void {
    $this->get(route('work_shifts.fetch'))->assertRedirect();
});

it('forbids fetch when user lacks view permission', function (): void {
    $company = Company::factory()->create();
    $user = $this->createAdminUser($company);
    $user->syncRoles([]);
    $user->syncPermissions([]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $this->actingAs($user)
        ->withSession(['current_company_id' => $company->id])
        ->getJson(route('work_shifts.fetch', ['order' => 'desc']))
        ->assertForbidden();
});

it('returns only current company records with meta and filter', function (): void {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $user = $this->createAdminUser($companyA);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    WorkShift::factory()->count(5)->create(['company_id' => $companyA->id]);
    WorkShift::factory()->count(4)->create(['company_id' => $companyB->id]);

    $response = $this->actingAs($user)
        ->withSession(['current_company_id' => $companyA->id])
        ->getJson(route('work_shifts.fetch', [
            'page' => 1,
            'per_page' => 10,
            'order' => 'desc',
        ]));

    $response->assertOk()
        ->assertJsonStructure([
            'message',
            'data',
            'meta' => ['current_page', 'per_page', 'total', 'last_page'],
            'filter',
        ]);

    expect($response->json('meta.total'))->toBe(5);
    expect(collect($response->json('data'))->pluck('company_id')->unique()->all())->toBe([$companyA->id]);
});
