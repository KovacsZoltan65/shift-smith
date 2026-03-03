<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\CompanyEmployee;
use App\Models\Employee;
use App\Models\TenantGroup;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('átirányítja a vendégeket a bejelentkezéshez a dolgozók lekéréséhez', function (): void {
    $this->get(route('employees.fetch'))->assertRedirect();
});

it('megtagadja az alkalmazottak lekérését, ha a felhasználónak nincs engedélye', function (): void {
    $user = $this->createAdminUser();
    $user->syncRoles([]);
    $user->syncPermissions([]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $this->actingAs($user)
        ->getJson(route('employees.fetch', ['order' => 'desc']))
        ->assertForbidden();
});

it('A fetch lapozott alkalmazottakat ad vissza, és támogatja a company_id + keresési szűrőket.', function (): void {
    $tenant = TenantGroup::factory()->create();
    $c1 = Company::factory()->create(['tenant_group_id' => $tenant->id, 'name' => 'Alpha Kft.']);
    $c2 = Company::factory()->create(['tenant_group_id' => $tenant->id, 'name' => 'Beta Kft.']);

    $user = $this->createAdminUser($c1);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $employeeOne = Employee::factory()->create([
        'company_id' => $c1->id,
        'first_name' => 'Nagy',
        'last_name'  => 'Anna',
        'email'      => 'nagy.anna@example.com',
    ]);

    $employeeTwo = Employee::factory()->create([
        'company_id' => $c2->id,
        'first_name' => 'Kiss',
        'last_name'  => 'Béla',
        'email'      => 'kiss.bela@example.com',
    ]);

    CompanyEmployee::query()->updateOrCreate(
        ['company_id' => $c1->id, 'employee_id' => $employeeOne->id],
        ['active' => true]
    );
    CompanyEmployee::query()->updateOrCreate(
        ['company_id' => $c2->id, 'employee_id' => $employeeTwo->id],
        ['active' => true]
    );

    $tenantSession = [
        'current_company_id' => (int) $c1->id,
        'current_tenant_group_id' => (int) $tenant->id,
    ];

    // company_id filter
    $this->actingAs($user)
        ->withSession($tenantSession)
        ->getJson(route('employees.fetch', [
            'company_id' => $c1->id,
            'page' => 1,
            'per_page' => 10,
        ]))
        ->assertOk()
        ->assertJsonPath('meta.current_page', 1)
        ->assertJsonCount(2, 'data');

    // search filter (név/email)
    $this->actingAs($user)
        ->withSession($tenantSession)
        ->getJson(route('employees.fetch', [
            'search' => 'Anna',
            'page' => 1,
            'per_page' => 10,
        ]))
        ->assertOk()
        ->assertJsonCount(1, 'data');
});
