<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\CompanyEmployee;
use App\Models\Employee;
use App\Models\TenantGroup;
use App\Models\UserEmployee;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('selector returns only active companies ordered by name', function (): void {
    $tenant = TenantGroup::factory()->create();

    $activeA = Company::factory()->create(['tenant_group_id' => $tenant->id, 'name' => 'A Active', 'active' => true]);
    $activeB = Company::factory()->create(['tenant_group_id' => $tenant->id, 'name' => 'B Active', 'active' => true]);
    $inactive = Company::factory()->create(['tenant_group_id' => $tenant->id, 'name' => 'C Inactive', 'active' => false]);
    $e1 = Employee::factory()->create(['company_id' => $activeA->id]);
    $e2 = Employee::factory()->create(['company_id' => $activeB->id]);
    CompanyEmployee::query()->updateOrCreate(['company_id' => $activeA->id, 'employee_id' => $e1->id], ['active' => true]);
    CompanyEmployee::query()->updateOrCreate(['company_id' => $activeB->id, 'employee_id' => $e2->id], ['active' => true]);
    CompanyEmployee::query()->updateOrCreate(['company_id' => $inactive->id, 'employee_id' => $e1->id], ['active' => true]);

    $user = $this->createAdminUser($activeA);
    UserEmployee::query()->where('user_id', $user->id)->delete();
    UserEmployee::query()->updateOrCreate(['user_id' => $user->id, 'employee_id' => $e1->id], ['active' => true]);
    UserEmployee::query()->updateOrCreate(['user_id' => $user->id, 'employee_id' => $e2->id], ['active' => true]);

    $tenant->makeCurrent();
    $resp = $this->actingAs($user)->withSession([
        'current_company_id' => $activeA->id,
        'current_tenant_group_id' => $tenant->id,
    ])->getJson(route('selectors.companies'));
    $resp->assertOk();

    $data = $resp->json();

    // 1) csak aktívak jöjjenek vissza
    foreach ($data as $row) {
        $company = Company::query()->find((int) $row['id']);
        expect($company)->not->toBeNull();
        expect((bool) $company->active)->toBeTrue();
    }

    // 2) a mi aktívjaink benne vannak, az inaktív nincs
    $ids = array_map('intval', array_column($data, 'id'));

    expect($ids)->toContain($activeA->id);
    expect($ids)->toContain($activeB->id);
    expect($ids)->not->toContain($inactive->id);

    // 3) rendezés név szerint (legalább a mi két elemünk relatív sorrendje)
    $posA = array_search($activeA->id, $ids, true);
    $posB = array_search($activeB->id, $ids, true);

    expect($posA)->not->toBeFalse();
    expect($posB)->not->toBeFalse();
    expect($posA)->toBeLessThan($posB);
});

it('selector can filter only companies with employees', function (): void {
    $tenant = TenantGroup::factory()->create();

    $withEmployees = Company::factory()->create(['tenant_group_id' => $tenant->id, 'name' => 'With Emp', 'active' => true]);
    $withoutEmployees = Company::factory()->create(['tenant_group_id' => $tenant->id, 'name' => 'No Emp', 'active' => true]);

    $e1 = Employee::factory()->create(['company_id' => $withEmployees->id]);
    CompanyEmployee::query()->updateOrCreate(['company_id' => $withEmployees->id, 'employee_id' => $e1->id], ['active' => true]);

    $user = $this->createAdminUser($withEmployees);
    UserEmployee::query()->where('user_id', $user->id)->delete();
    UserEmployee::query()->updateOrCreate(['user_id' => $user->id, 'employee_id' => $e1->id], ['active' => true]);

    $tenant->makeCurrent();
    $resp = $this->actingAs($user)->withSession([
        'current_company_id' => $withEmployees->id,
        'current_tenant_group_id' => $tenant->id,
    ])->getJson(route('selectors.companies', ['only_with_employees' => 1]));
    $resp->assertOk();

    $data = $resp->json();
    expect($data)->toHaveCount(1);
    expect($data[0]['id'])->toBe($withEmployees->id);
    expect($data[0]['name'])->toBe('With Emp');

    $ids = array_column($data, 'id');
    expect($ids)->not->toContain($withoutEmployees->id);
});
