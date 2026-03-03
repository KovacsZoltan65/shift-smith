<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\WorkShift;
use App\Services\Cache\CacheVersionService;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('forbids delete without permission', function (): void {
    $company = Company::factory()->create();
    $workShift = WorkShift::factory()->create(['company_id' => $company->id]);
    $user = $this->createAdminUser($company);
    $user->syncPermissions([]);
    $user->syncRoles([]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $this->actingAs($user)
        ->withSession([
            'current_company_id' => $company->id,
            'current_tenant_group_id' => $company->tenant_group_id,
        ])
        ->deleteJson(route('work_shifts.destroy', ['id' => $workShift->id]))
        ->assertRedirect();
});

it('soft deletes scoped shift and bumps cache versions', function (): void {
    $company = Company::factory()->create();
    $workShift = WorkShift::factory()->create(['company_id' => $company->id]);
    $user = $this->createAdminUser($company);
    $versioner = app(CacheVersionService::class);

    $beforeFetch = $versioner->get('work_shifts.fetch');
    $beforeSelector = $versioner->get('selectors.work_shifts');

    $this->actingAs($user)
        ->withSession([
            'current_company_id' => $company->id,
            'current_tenant_group_id' => $company->tenant_group_id,
        ])
        ->deleteJson(route('work_shifts.destroy', ['id' => $workShift->id]))
        ->assertOk();

    $this->assertSoftDeleted('work_shifts', ['id' => $workShift->id]);
    expect($versioner->get('work_shifts.fetch'))->toBeGreaterThan($beforeFetch);
    expect($versioner->get('selectors.work_shifts'))->toBeGreaterThan($beforeSelector);
});
