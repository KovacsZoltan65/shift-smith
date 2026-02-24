<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Employee;
use App\Models\TenantGroup;
use App\Repositories\CompanyRepository;
use App\Repositories\EmployeeRepository;
use App\Services\Cache\CacheVersionService;

beforeEach(function (): void {
    // Biztosítsuk, hogy nincs áthúzódó tenant kontextus tesztek között
    TenantGroup::forgetCurrent();
});

afterEach(function (): void {
    TenantGroup::forgetCurrent();
});

it('invalidates company selector cache through selectors.companies namespace (tenant-aware)', function (): void {
    config()->set('cache.enable_companyToSelect', true);

    $repo = app(CompanyRepository::class);
    $versions = app(CacheVersionService::class);

    $tg = TenantGroup::factory()->create(['active' => true]);
    $tg->makeCurrent();

    $first = Company::factory()->create([
        'tenant_group_id' => $tg->id,
        'name' => 'Alpha Co',
        'active' => true,
    ]);

    $initial = $repo->getToSelect(['only_with_employees' => false]);
    expect(array_column($initial, 'id'))->toContain($first->id);

    $second = Company::factory()->create([
        'tenant_group_id' => $tg->id,
        'name' => 'Beta Co',
        'active' => true,
    ]);

    // Ha a repository a selectors.companies namespace-t használja,
    // ez a bump tenant-szinten érvényteleníti a korábbi selector cache-t.
    $versions->bump('selectors.companies');

    $afterBump = $repo->getToSelect(['only_with_employees' => false]);
    expect(array_column($afterBump, 'id'))->toContain($second->id);
});

it('invalidates employee selector cache through selectors.employees namespace (tenant-aware)', function (): void {
    config()->set('cache.enable_employeeToSelect', true);

    $repo = app(EmployeeRepository::class);
    $versions = app(CacheVersionService::class);

    $tg = TenantGroup::factory()->create(['active' => true]);
    $tg->makeCurrent();

    $company = Company::factory()->create([
        'tenant_group_id' => $tg->id,
        'active' => true,
    ]);

    $first = Employee::factory()->create([
        'company_id' => $company->id,
        'active' => true,
    ]);

    $initial = $repo->getToSelect([
        'company_id' => $company->id,
        'only_active' => true,
    ]);

    expect(array_column($initial, 'id'))->toContain($first->id);

    $second = Employee::factory()->create([
        'company_id' => $company->id,
        'active' => true,
    ]);

    // Ha a repository a selectors.employees namespace-t használja,
    // ez a bump tenant-szinten érvényteleníti a korábbi selector cache-t.
    $versions->bump('selectors.employees');

    $afterBump = $repo->getToSelect([
        'company_id' => $company->id,
        'only_active' => true,
    ]);

    expect(array_column($afterBump, 'id'))->toContain($second->id);
});