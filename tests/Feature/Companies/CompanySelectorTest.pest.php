<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Employee;
use Tests\Support\CreatesUsers;

uses(CreatesUsers::class);

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('selector returns only active companies ordered by name', function (): void {
    $user = $this->createAdminUser();

    $activeA = Company::factory()->create(['name' => 'A Active', 'active' => true]);
    $activeB = Company::factory()->create(['name' => 'B Active', 'active' => true]);
    $inactive = Company::factory()->create(['name' => 'C Inactive', 'active' => false]);

    $resp = $this->actingAs($user)->getJson(route('selectors.companies'));
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
    $user = $this->createAdminUser();

    $withEmployees = Company::factory()->create(['name' => 'With Emp', 'active' => true]);
    $withoutEmployees = Company::factory()->create(['name' => 'No Emp', 'active' => true]);

    Employee::factory()->count(2)->create(['company_id' => $withEmployees->id]);

    $resp = $this->actingAs($user)->getJson(route('selectors.companies', ['only_with_employees' => 1]));
    $resp->assertOk();

    $data = $resp->json();
    expect($data)->toHaveCount(1);
    expect($data[0]['id'])->toBe($withEmployees->id);
    expect($data[0]['name'])->toBe('With Emp');

    $ids = array_column($data, 'id');
    expect($ids)->not->toContain($withoutEmployees->id);
});
