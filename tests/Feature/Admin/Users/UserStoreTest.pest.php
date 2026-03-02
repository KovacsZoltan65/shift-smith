<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\CompanyEmployee;
use App\Models\Employee;
use App\Models\User;
use App\Models\UserEmployee;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('allows admin to create a user with only name and email', function (): void {
    $company = Company::factory()->create();
    $admin = $this->createAdminUser($company);
    $admin->givePermissionTo('users.create');

    $response = $this
        ->actingAsUserInCompany($admin, $company)
        ->postJson(route('users.store'), [
            'name' => 'Teszt Felhasznalo',
            'email' => 'uj-felhasznalo@example.test',
            'company_id' => (int) $company->id,
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('name', 'Teszt Felhasznalo')
        ->assertJsonPath('email', 'uj-felhasznalo@example.test');

    $user = User::query()->where('email', 'uj-felhasznalo@example.test')->first();

    expect($user)->not->toBeNull()
        ->and((string) $user->password)->not->toBe('');
});

it('creates a user already attached to the selected current company', function (): void {
    $company = Company::factory()->create();
    $admin = $this->createAdminUser($company);
    $admin->givePermissionTo('users.create');

    $response = $this
        ->actingAsUserInCompany($admin, $company)
        ->postJson(route('users.store'), [
            'name' => 'Tenant User',
            'email' => 'tenant-user@example.test',
            'company_id' => (int) $company->id,
        ]);

    $response->assertOk();

    $user = User::query()->where('email', 'tenant-user@example.test')->firstOrFail();

    expect($user->companies()->pluck('companies.id')->all())->toBe([(int) $company->id]);
});

it('updates the user company assignment and removes old employee mappings when moved to another company', function (): void {
    $tenant = \App\Models\TenantGroup::factory()->create();
    $companyA = Company::factory()->create(['tenant_group_id' => (int) $tenant->id, 'active' => true]);
    $companyB = Company::factory()->create(['tenant_group_id' => (int) $tenant->id, 'active' => true]);

    $admin = $this->createAdminUser($companyA);
    $admin->companies()->syncWithoutDetaching([(int) $companyB->id]);
    $admin->givePermissionTo('users.update');

    $target = User::factory()->create();
    $target->companies()->sync([(int) $companyA->id]);

    $employee = Employee::factory()->create([
        'company_id' => (int) $companyA->id,
        'active' => true,
    ]);

    CompanyEmployee::query()->updateOrCreate(
        [
            'company_id' => (int) $companyA->id,
            'employee_id' => (int) $employee->id,
        ],
        ['active' => true]
    );

    UserEmployee::query()->create([
        'user_id' => (int) $target->id,
        'company_id' => (int) $companyA->id,
        'employee_id' => (int) $employee->id,
        'active' => true,
    ]);

    $response = $this
        ->actingAsUserInCompany($admin, $companyA)
        ->putJson(route('users.update', ['id' => $target->id]), [
            'name' => 'Moved User',
            'email' => (string) $target->email,
            'company_id' => (int) $companyB->id,
        ]);

    $response->assertOk();

    $target->refresh();

    expect($target->companies()->pluck('companies.id')->all())->toBe([(int) $companyB->id])
        ->and(UserEmployee::query()
            ->where('user_id', (int) $target->id)
            ->where('company_id', (int) $companyA->id)
            ->exists())->toBeFalse();
});
