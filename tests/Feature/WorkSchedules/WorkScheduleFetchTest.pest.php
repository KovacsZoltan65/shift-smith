<?php

declare(strict_types=1);

use App\Models\WorkSchedule;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('megtagadja a fetch műveletet viewAny jogosultság nélkül', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($company);
    $user->syncRoles([]);
    $user->syncPermissions([]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();
    $tenant->makeCurrent();

    $this->actingAsUserInCompany($user, $company)
        ->getJson(route('work_schedules.fetch'))
        ->assertRedirect();
});

it('company és tenant scope szerint szűri a munkabeosztásokat', function (): void {
    [$tenantA, $companyA] = $this->createTenantWithCompany();
    [, $companyWithinTenantA] = $this->createTenantWithCompany([], ['tenant_group_id' => $tenantA->id]);
    [$tenantB, $companyB] = $this->createTenantWithCompany();

    $user = $this->createAdminUser($companyA);

    WorkSchedule::factory()->count(2)->create(['company_id' => $companyA->id]);
    WorkSchedule::factory()->count(2)->create(['company_id' => $companyWithinTenantA->id]);
    WorkSchedule::factory()->count(2)->create(['company_id' => $companyB->id]);

    $tenantA->makeCurrent();

    $response = $this->actingAsUserInCompany($user, $companyA)
        ->getJson(route('work_schedules.fetch', [
            'page' => 1,
            'per_page' => 10,
        ]));

    $response->assertOk()->assertJsonStructure([
        'data',
        'meta' => ['current_page', 'per_page', 'total', 'last_page'],
        'filter',
    ]);

    expect($response->json('data'))->toHaveCount(2);
    foreach ($response->json('data') as $row) {
        expect($row['company_id'])->toBe($companyA->id);
    }
});
