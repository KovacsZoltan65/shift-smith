<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Position;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('átirányítja a vendéget a pozíció fetch végpontról', function (): void {
    $company = Company::factory()->create();
    $this->get(route('positions.fetch', ['company_id' => $company->id]))->assertRedirect();
});

it('megtagadja a fetch műveletet viewAny jogosultság nélkül', function (): void {
    $user = $this->createAdminUser();
    $user->syncRoles([]);
    $user->syncPermissions([]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();
    $company = Company::factory()->create();

    $this->actingAs($user)
        ->getJson(route('positions.fetch', ['company_id' => $company->id]))
        ->assertForbidden();
});

it('lapozott pozíció listát ad vissza', function (): void {
    $user = $this->createSuperadminUser();

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();
    $company = Company::factory()->create();

    Position::factory()->count(3)->create(['company_id' => $company->id]);

    $this->actingAs($user)
        ->withSession(['current_company_id' => $company->id])
        ->getJson(route('positions.fetch', ['page' => 1, 'per_page' => 10]))
        ->assertOk()
        ->assertJsonStructure([
            'data',
            'meta' => ['current_page', 'per_page', 'total', 'last_page'],
        ]);
});
