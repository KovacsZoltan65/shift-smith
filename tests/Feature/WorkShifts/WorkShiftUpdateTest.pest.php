<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\TenantGroup;
use App\Models\WorkShift;
use App\Models\WorkShiftBreak;
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
        ->withSession([
            'current_company_id' => $company->id,
            'current_tenant_group_id' => $company->tenant_group_id,
        ])
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
        ->withSession([
            'current_company_id' => $company->id,
            'current_tenant_group_id' => $company->tenant_group_id,
        ])
        ->putJson(route('work_shifts.update', ['id' => $workShift->id]), [
            'name' => 'Új',
            'start_time' => '09:00',
            'end_time' => '17:00',
            'breaks' => [
                ['break_start_time' => '13:00', 'break_end_time' => '13:30'],
            ],
            'active' => true,
        ])
        ->assertOk();

    $this->assertDatabaseHas('work_shifts', [
        'id' => $workShift->id,
        'company_id' => $company->id,
        'name' => 'Új',
        'break_minutes' => 30,
        'work_time_minutes' => 450,
    ]);
    $this->assertDatabaseHas('work_shift_breaks', [
        'work_shift_id' => $workShift->id,
        'company_id' => $company->id,
        'break_start_time' => '13:00:00',
        'break_end_time' => '13:30:00',
        'break_minutes' => 30,
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
        ->withSession([
            'current_company_id' => $companyA->id,
            'current_tenant_group_id' => $companyA->tenant_group_id,
        ])
        ->putJson(route('work_shifts.update', ['id' => $workShiftB->id]), [
            'name' => 'Tiltott',
            'start_time' => '08:00',
            'end_time' => '16:00',
            'active' => true,
        ])
        ->assertNotFound();
});

it('replaces break records and recomputes totals on update', function (): void {
    $company = Company::factory()->create();
    $workShift = WorkShift::factory()->create([
        'company_id' => $company->id,
        'start_time' => '08:00:00',
        'end_time' => '16:30:00',
        'break_minutes' => 30,
        'work_time_minutes' => 480,
    ]);
    WorkShiftBreak::query()->create([
        'company_id' => $company->id,
        'work_shift_id' => $workShift->id,
        'break_start_time' => '12:00:00',
        'break_end_time' => '12:30:00',
        'break_minutes' => 30,
    ]);
    $user = $this->createAdminUser($company);

    $this->actingAs($user)
        ->withSession([
            'current_company_id' => $company->id,
            'current_tenant_group_id' => $company->tenant_group_id,
        ])
        ->putJson(route('work_shifts.update', ['id' => $workShift->id]), [
            'name' => 'Frissített',
            'start_time' => '08:00',
            'end_time' => '16:30',
            'breaks' => [
                ['break_start_time' => '10:00', 'break_end_time' => '10:10'],
                ['break_start_time' => '14:00', 'break_end_time' => '14:20'],
            ],
            'active' => true,
        ])
        ->assertOk();

    $this->assertDatabaseHas('work_shifts', [
        'id' => $workShift->id,
        'break_minutes' => 30,
        'work_time_minutes' => 480,
    ]);
    $this->assertSoftDeleted('work_shift_breaks', [
        'work_shift_id' => $workShift->id,
        'break_start_time' => '12:00:00',
        'break_end_time' => '12:30:00',
    ]);
    $this->assertDatabaseHas('work_shift_breaks', [
        'work_shift_id' => $workShift->id,
        'break_start_time' => '10:00:00',
        'break_end_time' => '10:10:00',
        'break_minutes' => 10,
    ]);
    $this->assertDatabaseHas('work_shift_breaks', [
        'work_shift_id' => $workShift->id,
        'break_start_time' => '14:00:00',
        'break_end_time' => '14:20:00',
        'break_minutes' => 20,
    ]);
});

it('prevents cross-tenant work shift update even with numeric id', function (): void {
    $tenantA = TenantGroup::factory()->create();
    $tenantB = TenantGroup::factory()->create();
    $companyA = Company::factory()->create(['tenant_group_id' => $tenantA->id]);
    $companyB = Company::factory()->create(['tenant_group_id' => $tenantB->id]);
    $workShiftB = WorkShift::factory()->create(['company_id' => $companyB->id]);
    $user = $this->createAdminUser($companyA);

    $tenantA->makeCurrent();

    $this->actingAs($user)
        ->withSession([
            'current_company_id' => $companyB->id,
            'current_tenant_group_id' => $tenantA->id,
        ])
        ->putJson(route('work_shifts.update', ['id' => $workShiftB->id]), [
            'name' => 'Tiltott tenant update',
            'start_time' => '08:00',
            'end_time' => '16:00',
            'breaks' => [
                ['break_start_time' => '12:00', 'break_end_time' => '12:15'],
            ],
            'active' => true,
        ])
        ->assertRedirect();
});
