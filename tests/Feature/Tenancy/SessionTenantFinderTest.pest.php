<?php

declare(strict_types=1);

use App\Http\Middleware\InitializeTenantGroup;
use App\Models\TenantGroup;
use App\Models\User;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    TenantGroup::forgetCurrent();
});

it('does not make tenant current when current_tenant_group_id is missing from session', function (): void {
    Route::middleware(['web', InitializeTenantGroup::class])->get('/_test/tenancy/no-session-tenant', function () {
        return response()->json([
            'current_tenant_id' => TenantGroup::current()?->id,
        ]);
    });

    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/_test/tenancy/no-session-tenant')
        ->assertOk()
        ->assertJsonPath('current_tenant_id', null);
});

it('makes tenant current when current_tenant_group_id is present in session', function (): void {
    Route::middleware(['web', InitializeTenantGroup::class])->get('/_test/tenancy/with-session-tenant', function () {
        return response()->json([
            'current_tenant_id' => TenantGroup::current()?->id,
        ]);
    });

    $tenantGroup = TenantGroup::query()->create([
        'name' => 'Tenant A',
        'code' => 'TENANT_A',
        'slug' => 'tenant-a',
        'active' => true,
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['current_tenant_group_id' => $tenantGroup->id])
        ->getJson('/_test/tenancy/with-session-tenant')
        ->assertOk()
        ->assertJsonPath('current_tenant_id', $tenantGroup->id);
});
