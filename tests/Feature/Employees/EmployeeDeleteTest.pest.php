<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Employee;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('törli az alkalmazottat', function (): void {
    $user = $this->createAdminUser();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $company = Company::factory()->create();
    $employee = Employee::factory()->create(['company_id' => $company->id]);

    $this->actingAs($user)
        ->deleteJson(route('employees.destroy', $employee->id))
        ->assertOk();

    $this->assertSoftDeleted('employees', ['id' => $employee->id]);
});
