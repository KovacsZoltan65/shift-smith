<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\TenantGroup;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
    TenantGroup::forgetCurrent();
});

afterEach(function (): void {
    TenantGroup::forgetCurrent();
});

it('stores work shift on web route with selected company session context (no 419)', function (): void {
    $tenant = TenantGroup::factory()->create();
    $company = Company::factory()->create([
        'tenant_group_id' => $tenant->id,
        'active' => true,
    ]);
    $user = $this->createAdminUser($company);

    $this->actingAs($user)
        ->withSession([
            'selected_company_id' => (int) $company->id,
            'current_tenant_group_id' => (int) $tenant->id,
        ])
        ->post(route('work_shifts.store'), [
            'name' => 'CSRF Shift',
            'start_time' => '08:00',
            'end_time' => '16:00',
            'work_time_minutes' => 480,
            'break_minutes' => 30,
            'active' => true,
        ])
        ->assertCreated();

    $this->assertDatabaseHas('work_shifts', [
        'company_id' => (int) $company->id,
        'name' => 'CSRF Shift',
    ]);
});

