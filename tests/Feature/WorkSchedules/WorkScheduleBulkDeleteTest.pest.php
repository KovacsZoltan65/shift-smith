<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\TenantGroup;
use App\Models\WorkSchedule;
use App\Services\Cache\CacheNamespaces;
use App\Services\Cache\CacheVersionService;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('denies bulk delete if user lacks permission', function (): void {
    $user = $this->createAdminUser();
    $user->syncPermissions([]);
    $user->syncRoles([]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $tenant = TenantGroup::factory()->create();
    $company = Company::factory()->create(['tenant_group_id' => $tenant->id]);
    $ws1 = WorkSchedule::factory()->create(['company_id' => $company->id, 'status' => 'draft']);
    $ws2 = WorkSchedule::factory()->create(['company_id' => $company->id, 'status' => 'draft']);

    $this
        ->actingAsUserInCompany($user, $company)
        ->deleteJson(route('work_schedules.destroy_bulk'), ['ids' => [$ws1->id, $ws2->id]])
        ->assertForbidden();
});

it('prevents bulk delete if any published is included', function (): void {
    [, $company] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($company);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $draft = WorkSchedule::factory()->create(['company_id' => $company->id, 'status' => 'draft']);
    $pub = WorkSchedule::factory()->create(['company_id' => $company->id, 'status' => 'published']);

    $this
        ->actingAsUserInCompany($user, $company)
        ->deleteJson(route('work_schedules.destroy_bulk'), ['ids' => [$draft->id, $pub->id]])
        ->assertUnprocessable();

    $this->assertDatabaseHas('work_schedules', ['id' => $draft->id, 'deleted_at' => null]);
    $this->assertDatabaseHas('work_schedules', ['id' => $pub->id, 'deleted_at' => null]);
});

it('allows admin to bulk delete drafts and bumps cache versions', function (): void {
    [, $company] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($company);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $ws = WorkSchedule::factory()->count(3)->create(['company_id' => $company->id, 'status' => 'draft']);

    $versioner = app(CacheVersionService::class);
    $tenantNamespace = CacheNamespaces::tenantWorkSchedules((int) $company->tenant_group_id);
    $companyNamespace = "company:{$company->id}:work_schedules";
    $tenantBefore = $versioner->get($tenantNamespace);
    $companyBefore = $versioner->get($companyNamespace);

    $ids = $ws->pluck('id')->all();

    $this
        ->actingAsUserInCompany($user, $company)
        ->deleteJson(route('work_schedules.destroy_bulk'), ['ids' => $ids])
        ->assertOk()
        ->assertJson(['deleted' => 3]);

    foreach ($ids as $id) {
        $this->assertSoftDeleted('work_schedules', ['id' => $id]);
    }

    expect($versioner->get($tenantNamespace))->toBeGreaterThan($tenantBefore);
    expect($versioner->get($companyNamespace))->toBeGreaterThan($companyBefore);
});
