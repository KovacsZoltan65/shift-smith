<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureTenantContext;
use App\Models\TenantGroup;
use App\Repositories\WorkShiftRepository;
use App\Support\CurrentCompanyContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\Support\InteractsWithTenantSession;

uses(InteractsWithTenantSession::class);

beforeEach(function (): void {
    TenantGroup::forgetCurrent();

    if (! Route::has('test.tenancy.work-shifts.show')) {
        Route::middleware(['web', EnsureTenantContext::class])
            ->get('/_test/tenancy/work-shifts/{shift}', function (
                int $shift,
                Request $request,
                CurrentCompanyContext $companyContext,
                WorkShiftRepository $repository
            ) {
                $companyId = $companyContext->resolve($request);
                $workShift = $repository->findOrFailScoped($shift, $companyId);

                return response()->json([
                    'id' => (int) $workShift->id,
                    'company_id' => (int) $workShift->company_id,
                ]);
            })
            ->name('test.tenancy.work-shifts.show');
    }
});

afterEach(function (): void {
    TenantGroup::forgetCurrent();
});

it('prevents company A from reading company B shift data inside the same tenant', function (): void {
    [$tenant, $companyA] = $this->createTenantWithCompany();
    [, $companyB] = $this->createTenantWithCompany([], ['tenant_group_id' => $tenant->id]);

    $shift = \App\Models\WorkShift::factory()->create([
        'company_id' => (int) $companyB->id,
    ]);

    $response = $this
        ->withSession([
            'current_tenant_group_id' => (int) $tenant->id,
            'current_company_id' => (int) $companyA->id,
        ])
        ->get("/_test/tenancy/work-shifts/{$shift->id}");

    $response->assertNotFound();
});

it('allows reading shift data for the active company only', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $shift = \App\Models\WorkShift::factory()->create([
        'company_id' => (int) $company->id,
    ]);

    $response = $this
        ->withSession([
            'current_tenant_group_id' => (int) $tenant->id,
            'current_company_id' => (int) $company->id,
        ])
        ->get("/_test/tenancy/work-shifts/{$shift->id}");

    $response
        ->assertOk()
        ->assertJson([
            'id' => (int) $shift->id,
            'company_id' => (int) $company->id,
        ]);
});
