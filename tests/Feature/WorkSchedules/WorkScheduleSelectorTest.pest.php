<?php

declare(strict_types=1);

use App\Models\WorkSchedule;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('megtagadja a selector műveletet viewAny jogosultság nélkül', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($company);
    $user->syncRoles([]);
    $user->syncPermissions([]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();
    $tenant->makeCurrent();

    $this->actingAsUserInCompany($user, $company)
        ->getJson(route('work_schedules.selector', ['company_id' => $company->id]))
        ->assertRedirect();
});

it('only_published esetén csak a scoped publikált munkabeosztásokat adja vissza', function (): void {
    [$tenantA, $companyA] = $this->createTenantWithCompany();
    [, $companyWithinTenantA] = $this->createTenantWithCompany([], ['tenant_group_id' => $tenantA->id]);
    [$tenantB, $companyB] = $this->createTenantWithCompany();

    $user = $this->createAdminUser($companyA);

    WorkSchedule::factory()->create([
        'company_id' => $companyA->id,
        'name' => 'A publikált',
        'status' => 'published',
        'date_from' => '2026-02-01',
    ]);
    WorkSchedule::factory()->create([
        'company_id' => $companyA->id,
        'name' => 'A draft',
        'status' => 'draft',
        'date_from' => '2026-01-01',
    ]);
    WorkSchedule::factory()->create([
        'company_id' => $companyWithinTenantA->id,
        'name' => 'Másik cég',
        'status' => 'published',
    ]);
    WorkSchedule::factory()->create([
        'company_id' => $companyB->id,
        'name' => 'Másik tenant',
        'status' => 'published',
    ]);

    $tenantA->makeCurrent();

    $response = $this->actingAsUserInCompany($user, $companyA)
        ->getJson(route('work_schedules.selector', [
            'company_id' => $companyA->id,
            'only_published' => 1,
        ]));

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.name'))->toBe('A publikált')
        ->and($response->json('data.0.status'))->toBe('published');
});
