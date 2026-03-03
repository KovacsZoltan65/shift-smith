<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\TenantGroup;
use App\Models\WorkShift;
use App\Repositories\WorkShiftRepository;
use App\Services\Cache\CacheVersionService;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

afterEach(function (): void {
    TenantGroup::forgetCurrent();
});

it('forbids work shift selector without view permission', function (): void {
    $company = Company::factory()->create();
    $user = $this->createAdminUser($company);
    $user->syncRoles([]);
    $user->syncPermissions([]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $this->actingAs($user)
        ->withSession([
            'current_company_id' => $company->id,
            'current_tenant_group_id' => $company->tenant_group_id,
        ])
        ->getJson(route('selectors.work_shifts'))
        ->assertRedirect();
});

it('returns minimal selector payload and supports search for current company', function (): void {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $user = $this->createAdminUser($companyA);

    WorkShift::factory()->create(['company_id' => $companyA->id, 'name' => 'Reggeli', 'active' => true]);
    WorkShift::factory()->create(['company_id' => $companyA->id, 'name' => 'Delutani', 'active' => true]);
    WorkShift::factory()->create(['company_id' => $companyB->id, 'name' => 'Masik ceges', 'active' => true]);

    $response = $this->actingAs($user)
        ->withSession([
            'current_company_id' => $companyA->id,
            'current_tenant_group_id' => $companyA->tenant_group_id,
        ])
        ->getJson(route('selectors.work_shifts', ['search' => 'regg']));

    $response->assertOk();
    $data = $response->json();

    expect($data)->toBeArray();
    expect(array_column($data, 'name'))->toContain('Reggeli');
    expect(array_column($data, 'name'))->not->toContain('Masik ceges');
});

it('selector cache namespace reacts to selectors.work_shifts bump', function (): void {
    config()->set('cache.enable_work_shiftToSelect', true);

    $company = Company::factory()->create();
    $repo = app(WorkShiftRepository::class);
    $versioner = app(CacheVersionService::class);
    $tenant = TenantGroup::query()->find($company->tenant_group_id);
    $tenant?->makeCurrent();

    WorkShift::factory()->create(['company_id' => $company->id, 'name' => 'A Shift', 'active' => true]);

    $first = $repo->getToSelect([
        'company_id' => $company->id,
        'only_active' => true,
    ], $company->id);

    expect(array_column($first, 'name'))->toContain('A Shift');

    WorkShift::factory()->create(['company_id' => $company->id, 'name' => 'B Shift', 'active' => true]);
    $versioner->bump('selectors.work_shifts');

    $second = $repo->getToSelect([
        'company_id' => $company->id,
        'only_active' => true,
    ], $company->id);

    expect(array_column($second, 'name'))->toContain('B Shift');
});
