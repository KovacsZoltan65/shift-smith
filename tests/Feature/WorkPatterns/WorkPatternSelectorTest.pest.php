<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\WorkPattern;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('átirányítja a vendéget a munkarend selector végpontról', function (): void {
    $company = Company::factory()->create();

    $this->get(route('selectors.work_patterns', ['company_id' => $company->id]))->assertRedirect();
});

it('megtagadja a selector lekérést jogosultság nélkül', function (): void {
    $user = $this->createAdminUser();
    $user->syncRoles([]);
    $user->syncPermissions([]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $company = Company::factory()->create();

    $this->actingAs($user)
        ->getJson(route('selectors.work_patterns', ['company_id' => $company->id]))
        ->assertForbidden();
});

it('visszaadja az aktív munkarendeket selector formában', function (): void {
    $user = $this->createAdminUser();

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $company = Company::factory()->create();

    WorkPattern::factory()->create(['company_id' => $company->id, 'name' => 'Aktív', 'active' => true]);
    WorkPattern::factory()->create(['company_id' => $company->id, 'name' => 'Inaktív', 'active' => false]);

    $resp = $this->actingAs($user)
        ->getJson(route('selectors.work_patterns', ['company_id' => $company->id]));

    $resp->assertOk()->assertJsonIsArray();

    $first = $resp->json()[0] ?? null;
    expect($first)->toBeArray();
    expect($first)->toHaveKeys(['id', 'name']);
    expect(collect($resp->json())->pluck('name')->all())->toContain('Aktív');
    expect(collect($resp->json())->pluck('name')->all())->not->toContain('Inaktív');
});
