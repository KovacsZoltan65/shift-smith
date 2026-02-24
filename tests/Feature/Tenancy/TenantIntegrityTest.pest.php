<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\TenantGroup;
use App\Services\CurrentTenantGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

afterEach(function (): void {
    \Mockery::close();
});

beforeEach(function (): void {
    Route::middleware(['web', 'auth', 'ensure.company'])
        ->get('/_test/tenancy/integrity-protected', function () {
            return response()->json(['ok' => true]);
        });
});

it('aborts with 500 and logs error when tenant_group_id is missing in auto-select index flow', function (): void {
    $this->seedRolesAndPermissions();

    $tenantGroup = TenantGroup::factory()->create();
    $company = Company::factory()->create([
        'tenant_group_id' => $tenantGroup->id,
        'active' => true,
    ]);
    $user = $this->createAdminUser($company);

    DB::statement('ALTER TABLE companies MODIFY tenant_group_id BIGINT UNSIGNED NULL');
    Company::query()->whereKey($company->id)->update(['tenant_group_id' => null]);

    Log::spy();

    $this->actingAs($user)
        ->get(route('company.select'))
        ->assertStatus(500);

    Log::shouldHaveReceived('error')
        ->once()
        ->withArgs(function (string $message, array $context) use ($company): bool {
            return $message === 'company.missing_tenant_group_id'
                && (int) ($context['company_id'] ?? 0) === (int) $company->id;
        });
});

it('aborts with 500 and logs error when tenant_group_id is missing on explicit store flow', function (): void {
    $this->seedRolesAndPermissions();

    $tenantGroup = TenantGroup::factory()->create();
    $company = Company::factory()->create([
        'tenant_group_id' => $tenantGroup->id,
        'active' => true,
    ]);
    $user = $this->createAdminUser($company);

    DB::statement('ALTER TABLE companies MODIFY tenant_group_id BIGINT UNSIGNED NULL');
    Company::query()->whereKey($company->id)->update(['tenant_group_id' => null]);

    Log::spy();

    $this->actingAs($user)
        ->post(route('company.select.store'), ['company_id' => $company->id])
        ->assertStatus(500);

    Log::shouldHaveReceived('error')
        ->once()
        ->withArgs(function (string $message, array $context) use ($company): bool {
            return $message === 'company.missing_tenant_group_id'
                && (int) ($context['company_id'] ?? 0) === (int) $company->id;
        });
});

it('clears session and logs warning when invalid tenant group id is set', function (): void {
    $session = app('session.store');
    $session->start();
    $session->put('current_tenant_group_id', 42);

    $request = Request::create('/_test/tenancy/invalid-tenant-group-id', 'GET');
    $request->setLaravelSession($session);

    Log::spy();

    app(CurrentTenantGroup::class)->setCurrentTenantGroupId($request, 0);

    expect($session->has('current_tenant_group_id'))->toBeFalse();

    Log::shouldHaveReceived('warning')
        ->once()
        ->withArgs(function (string $message, array $context): bool {
            return $message === 'tenant_group.invalid_id'
                && (int) ($context['tenant_group_id'] ?? -1) === 0;
        });
});

it('middleware auto-select logs error and redirects when single selectable company has missing tenant_group_id', function (): void {
    $this->seedRolesAndPermissions();

    $tenantGroup = TenantGroup::factory()->create();
    $company = Company::factory()->create([
        'tenant_group_id' => $tenantGroup->id,
        'active' => true,
    ]);
    $user = $this->createAdminUser($company);

    DB::statement('ALTER TABLE companies MODIFY tenant_group_id BIGINT UNSIGNED NULL');
    Company::query()->whereKey($company->id)->update(['tenant_group_id' => null]);

    Log::spy();

    $this->actingAs($user)
        ->get('/_test/tenancy/integrity-protected')
        ->assertRedirect(route('company.select'))
        ->assertSessionMissing('current_company_id')
        ->assertSessionMissing('current_tenant_group_id');

    Log::shouldHaveReceived('error')
        ->atLeast()
        ->once()
        ->withArgs(function (string $message, array $context) use ($company, $user): bool {
            return $message === 'company.missing_tenant_group_id'
                && (int) ($context['company_id'] ?? 0) === (int) $company->id
                && (int) ($context['user_id'] ?? 0) === (int) $user->id;
        });
});
