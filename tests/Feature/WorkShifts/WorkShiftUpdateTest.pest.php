<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\WorkShift;
use App\Services\Cache\CacheVersionService;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('forbids update without permission', function (): void {
    $company = Company::factory()->create();
    $workShift = WorkShift::factory()->create(['company_id' => $company->id]);
    $user = $this->createAdminUser($company);
    $user->syncPermissions([]);
    $user->syncRoles([]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $this->actingAs($user)
        ->withSession(['current_company_id' => $company->id])
        ->putJson(route('work_shifts.update', ['id' => $workShift->id]), [
            'name' => 'X',
            'start_time' => '07:00',
            'end_time' => '15:00',
            'active' => true,
        ])
        ->assertForbidden();
});

it('updates scoped work shift and bumps cache versions', function (): void {
    $company = Company::factory()->create();
    $workShift = WorkShift::factory()->create([
        'company_id' => $company->id,
        'name' => 'Régi',
        'start_time' => '06:00:00',
        'end_time' => '14:00:00',
    ]);
    $user = $this->createAdminUser($company);
    $versioner = app(CacheVersionService::class);

    $beforeFetch = $versioner->get('work_shifts.fetch');
    $beforeSelector = $versioner->get('selectors.work_shifts');

    $this->actingAs($user)
        ->withSession(['current_company_id' => $company->id])
        ->putJson(route('work_shifts.update', ['id' => $workShift->id]), [
            'name' => 'Új',
            'start_time' => '09:00',
            'end_time' => '17:00',
            'work_time_minutes' => 450,
            'break_minutes' => 30,
            'active' => true,
        ])
        ->assertOk();

    $this->assertDatabaseHas('work_shifts', [
        'id' => $workShift->id,
        'company_id' => $company->id,
        'name' => 'Új',
    ]);

    expect($versioner->get('work_shifts.fetch'))->toBeGreaterThan($beforeFetch);
    expect($versioner->get('selectors.work_shifts'))->toBeGreaterThan($beforeSelector);
});

it('does not allow cross-company update', function (): void {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $workShiftB = WorkShift::factory()->create(['company_id' => $companyB->id]);
    $user = $this->createAdminUser($companyA);

    $this->actingAs($user)
        ->withSession(['current_company_id' => $companyA->id])
        ->putJson(route('work_shifts.update', ['id' => $workShiftB->id]), [
            'name' => 'Tiltott',
            'start_time' => '08:00',
            'end_time' => '16:00',
            'active' => true,
        ])
        ->assertNotFound();
});
