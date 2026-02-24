<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\WorkShift;
use App\Services\Cache\CacheVersionService;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('forbids bulk delete without deleteAny permission', function (): void {
    $company = Company::factory()->create();
    $workShifts = WorkShift::factory()->count(2)->create(['company_id' => $company->id]);
    $user = $this->createAdminUser($company);
    $user->syncPermissions([]);
    $user->syncRoles([]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $this->actingAs($user)
        ->withSession(['current_company_id' => $company->id])
        ->deleteJson(route('work_shifts.destroy_bulk'), ['ids' => $workShifts->pluck('id')->all()])
        ->assertForbidden();
});

it('bulk deletes scoped shifts and bumps cache versions', function (): void {
    $company = Company::factory()->create();
    $workShifts = WorkShift::factory()->count(3)->create(['company_id' => $company->id]);
    $ids = $workShifts->pluck('id')->all();
    $user = $this->createAdminUser($company);
    $versioner = app(CacheVersionService::class);

    $beforeFetch = $versioner->get('work_shifts.fetch');
    $beforeSelector = $versioner->get('selectors.work_shifts');

    $this->actingAs($user)
        ->withSession(['current_company_id' => $company->id])
        ->deleteJson(route('work_shifts.destroy_bulk'), ['ids' => $ids])
        ->assertOk()
        ->assertJson(['deleted' => 3]);

    foreach ($ids as $id) {
        $this->assertSoftDeleted('work_shifts', ['id' => $id]);
    }

    expect($versioner->get('work_shifts.fetch'))->toBeGreaterThan($beforeFetch);
    expect($versioner->get('selectors.work_shifts'))->toBeGreaterThan($beforeSelector);
});
