<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Company;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('átirányítja a vendéget a munkarend index oldalról', function (): void {
    $this->get(route('work_patterns.index'))->assertRedirect();
});

it('megtagadja a munkarend indexet jogosultság nélkül', function (): void {
    $user = $this->createAdminUser();
    $user->syncRoles([]);
    $user->syncPermissions([]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $this->actingAs($user)
        ->get(route('work_patterns.index'))
        ->assertForbidden();
});

it('engedi adminnak a munkarend index oldalt', function (): void {
    if (!file_exists(public_path('build/manifest.json'))) {
        $this->markTestSkipped('Vite build manifest hiányzik a környezetben.');
    }

    $user = $this->createAdminUser();
    $company = Company::factory()->create();

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $this->actingAs($user)
        ->get(route('work_patterns.index', ['company_id' => $company->id, 'search' => 'fix']))
        ->assertOk();
});
