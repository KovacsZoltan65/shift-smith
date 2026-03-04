<?php

declare(strict_types=1);

use App\Services\Cache\CacheVersionService;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('megtagadja a létrehozást jogosultság nélkül', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($company);
    $user->syncRoles([]);
    $user->syncPermissions([]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();
    $tenant->makeCurrent();

    $this->actingAsUserInCompany($user, $company)
        ->postJson(route('work_schedules.store'), [
            'company_id' => $company->id,
            'name' => 'Márciusi beosztás',
            'date_from' => '2026-03-01',
            'date_to' => '2026-03-31',
            'status' => 'draft',
        ])
        ->assertRedirect();
});

it('létrehozza a munkabeosztást és bumpolja a cache verziókat', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($company);
    $versioner = app(CacheVersionService::class);

    $tenant->makeCurrent();
    Cache::forever("v:company:{$company->id}:work_schedules", 1);
    Cache::forever('v:selectors.work_schedules', 1);

    $this->actingAsUserInCompany($user, $company)
        ->postJson(route('work_schedules.store'), [
            'company_id' => $company->id,
            'name' => 'Márciusi beosztás',
            'date_from' => '2026-03-01',
            'date_to' => '2026-03-31',
            'status' => 'draft',
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Márciusi beosztás');

    $this->assertDatabaseHas('work_schedules', [
        'company_id' => $company->id,
        'name' => 'Márciusi beosztás',
        'status' => 'draft',
    ]);

    expect($versioner->get("company:{$company->id}:work_schedules"))->toBe(2);
    expect($versioner->get('selectors.work_schedules'))->toBe(2);
});
