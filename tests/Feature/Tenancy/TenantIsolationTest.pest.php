<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureTenantContext;
use App\Models\TenantGroup;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\InteractsWithTenantSession;

uses(InteractsWithTenantSession::class);

beforeEach(function (): void {
    TenantGroup::forgetCurrent();

    if (! Route::has('test.tenancy.company.show')) {
        Route::middleware(['web', EnsureTenantContext::class])
            ->get('/_test/tenancy/company/{company}', function (int $company) {
                $resolved = resolveTenantScopedCompany($company);

                return response()->json([
                    'id' => (int) $resolved->id,
                    'tenant_group_id' => (int) $resolved->tenant_group_id,
                ]);
            })
            ->name('test.tenancy.company.show');
    }
});

afterEach(function (): void {
    TenantGroup::forgetCurrent();
});

it('prevents tenant A from resolving tenant B company data', function (): void {
    [$tenantA, $companyA] = $this->createTenantWithCompany();
    [, $companyB] = $this->createTenantWithCompany();

    $response = $this
        ->withSession([
            'current_tenant_group_id' => (int) $tenantA->id,
            'current_company_id' => (int) $companyA->id,
        ])
        ->get("/_test/tenancy/company/{$companyB->id}");

    $response->assertNotFound();
});

it('resolves the current tenant company when the company belongs to the active tenant', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();

    $response = $this
        ->withSession([
            'current_tenant_group_id' => (int) $tenant->id,
            'current_company_id' => (int) $company->id,
        ])
        ->get("/_test/tenancy/company/{$company->id}");

    $response
        ->assertOk()
        ->assertJson([
            'id' => (int) $company->id,
            'tenant_group_id' => (int) $tenant->id,
        ]);
});

it('returns 422 when tenant middleware runs without tenant context', function (): void {
    if (! Route::has('test.tenancy.context.ping')) {
        Route::middleware(['web', EnsureTenantContext::class])
            ->get('/_test/tenancy/context', fn () => response()->json(['ok' => true]))
            ->name('test.tenancy.context.ping');
    }

    $response = $this->get('/_test/tenancy/context');

    $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
});
