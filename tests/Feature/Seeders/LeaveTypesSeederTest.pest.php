<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\TenantGroup;
use Database\Seeders\LeaveTypesSeeder;

beforeEach(function (): void {
    TenantGroup::forgetCurrent();
});

afterEach(function (): void {
    TenantGroup::forgetCurrent();
});

it('seedeli a betegszabadsagot ugy hogy nem csokkenti a leave balance-t', function (): void {
    $tenant = TenantGroup::factory()->create();
    $company = Company::factory()->create([
        'tenant_group_id' => $tenant->id,
        'active' => true,
    ]);

    app(LeaveTypesSeeder::class)->run();

    $this->assertDatabaseHas('leave_types', [
        'company_id' => $company->id,
        'code' => 'sick_leave',
        'category' => 'sick_leave',
        'affects_leave_balance' => false,
    ]);
});
