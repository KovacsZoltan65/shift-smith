<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureTenantContext;
use App\Models\TenantGroup;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

beforeEach(function (): void {
    TenantGroup::forgetCurrent();

    if (! Route::has('test.tenancy.context.attributes')) {
        Route::middleware(['web', EnsureTenantContext::class])
            ->get('/_test/tenancy/attributes', function (\Illuminate\Http\Request $request) {
                return response()->json([
                    'tenant_group_id' => $request->attributes->get('tenant_group_id'),
                    'company_id' => $request->attributes->get('company_id'),
                ]);
            })
            ->name('test.tenancy.context.attributes');
    }
});

afterEach(function (): void {
    TenantGroup::forgetCurrent();
});

it('injects tenant context into the request when present', function (): void {
    $tenant = TenantGroup::factory()->create();

    $response = $this
        ->withSession([
            'current_tenant_group_id' => (int) $tenant->id,
        ])
        ->get('/_test/tenancy/attributes');

    $response
        ->assertOk()
        ->assertJson([
            'tenant_group_id' => (int) $tenant->id,
            'company_id' => null,
        ]);
});

it('throws 422 when tenant context is missing entirely', function (): void {
    $response = $this->get('/_test/tenancy/attributes');

    $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
});
