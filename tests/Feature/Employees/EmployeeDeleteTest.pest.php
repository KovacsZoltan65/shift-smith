<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Employee;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('törli az alkalmazottat', function (): void {
    $company = Company::factory()->create();
    $user = $this->createAdminUser($company);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $employee = Employee::factory()->create(['company_id' => $company->id]);

    $this->actingAs($user)
        ->withSession([
            'current_company_id' => (int) $company->id,
            'current_tenant_group_id' => (int) $company->tenant_group_id,
        ])
        ->deleteJson(route('employees.destroy', $employee->id))
        ->assertOk();

    $this->assertSoftDeleted('employees', ['id' => $employee->id]);
});
