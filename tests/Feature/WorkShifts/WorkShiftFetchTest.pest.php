<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\WorkShift;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('átirányítja a vendégeket a bejelentkezéshez a műszakok lekéréséhez', function (): void {
    $this->get(route('work_shifts.fetch'))->assertRedirect();
});

it('megtagadja a műszakok lekérését, ha nincs viewAny jogosultság', function (): void {
    $user = $this->createAdminUser();
    $user->syncRoles([]);
    $user->syncPermissions([]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $this->actingAs($user)
        ->getJson(route('work_shifts.fetch', ['order' => 'desc']))
        ->assertForbidden();
});

it('visszaadja a műszakokat meta + filter adatokkal', function (): void {
    $user = $this->createSuperadminUser();

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $company = Company::factory()->create();
    WorkShift::factory()->count(15)->create(['company_id' => $company->id]);

    $resp = $this
        ->actingAs($user)
        ->withSession(['current_company_id' => $company->id])
        ->getJson(route('work_shifts.fetch', [
            'page' => 1,
            'per_page' => 10,
            'order' => 'desc',
        ]));

    $resp
        ->assertOk()
        ->assertJsonStructure([
            'data',
            'meta' => ['current_page', 'per_page', 'total', 'last_page'],
            'filter',
        ]);

    expect($resp->json('data'))->toHaveCount(10);
    expect($resp->json('meta.total'))->toBe(15);
    expect($resp->json('filter.company_id'))->toBe($company->id);
});
