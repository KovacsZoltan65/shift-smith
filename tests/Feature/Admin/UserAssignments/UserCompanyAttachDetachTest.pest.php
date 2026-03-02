<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\CompanyEmployee;
use App\Models\Employee;
use App\Models\TenantGroup;
use App\Models\User;
use App\Models\UserEmployee;
use App\Services\Cache\CacheVersionService;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('attaches a company to a user within the current tenant', function (): void {
    $tenant = TenantGroup::factory()->create();
    $companyA = Company::factory()->create(['tenant_group_id' => $tenant->id, 'active' => true]);
    $companyB = Company::factory()->create(['tenant_group_id' => $tenant->id, 'active' => true]);

    $admin = $this->createAdminUser($companyA);
    $admin->givePermissionTo(['user_assignments.viewAny', 'user_assignments.update']);

    $target = User::factory()->create();
    $target->companies()->sync([$companyA->id]);

    Cache::flush();
    $versions = app(CacheVersionService::class);
    $namespace = "tenant:{$tenant->id}:selectors.companies";
    $before = $versions->get($namespace);

    $this->actingAsUserInCompany($admin, $companyA)
        ->postJson(route('admin.user_assignments.companies.store', ['user' => $target->id]), [
            'company_id' => $companyB->id,
        ])
        ->assertOk();

    $this->assertDatabaseHas('company_user', [
        'user_id' => (int) $target->id,
        'company_id' => (int) $companyB->id,
    ]);

    expect($versions->get($namespace))->toBeGreaterThan($before);
});

it('detaches a company and removes the related user employee mapping', function (): void {
    $tenant = TenantGroup::factory()->create();
    $companyA = Company::factory()->create(['tenant_group_id' => $tenant->id, 'active' => true]);
    $employee = Employee::factory()->create(['company_id' => $companyA->id, 'active' => true]);

    CompanyEmployee::query()->updateOrCreate(
        ['company_id' => $companyA->id, 'employee_id' => $employee->id],
        ['active' => true]
    );

    $admin = $this->createAdminUser($companyA);
    $admin->givePermissionTo(['user_assignments.viewAny', 'user_assignments.update']);

    $target = User::factory()->create();
    $target->companies()->sync([$companyA->id]);
    UserEmployee::query()->create([
        'user_id' => (int) $target->id,
        'company_id' => (int) $companyA->id,
        'employee_id' => (int) $employee->id,
        'active' => true,
    ]);

    $this->actingAsUserInCompany($admin, $companyA)
        ->deleteJson(route('admin.user_assignments.companies.destroy', [
            'user' => $target->id,
            'company' => $companyA->id,
        ]))
        ->assertOk();

    $this->assertDatabaseMissing('company_user', [
        'user_id' => (int) $target->id,
        'company_id' => (int) $companyA->id,
    ]);

    $this->assertDatabaseMissing('user_employee', [
        'user_id' => (int) $target->id,
        'company_id' => (int) $companyA->id,
    ]);
});
