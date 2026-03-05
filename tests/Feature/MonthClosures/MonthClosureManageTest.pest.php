<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\MonthClosure;
use App\Models\TenantGroup;
use App\Models\User;
use Carbon\CarbonImmutable;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('close and reopen csak megfelelő jogosultsággal engedélyezett', function (): void {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-03-15'));

    [$tenant, $company] = $this->createTenantWithCompany();

    /** @var User $user */
    $user = User::factory()->create();
    $user->assignRole('user');
    $user->companies()->syncWithoutDetaching([$company->id]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    $this->actingAsUserInCompany($user, $company)
        ->postJson(route('scheduling.month_closures.store'), [
            'year' => 2026,
            'month' => 3,
        ])
        ->assertForbidden();

    $closure = MonthClosure::factory()->create([
        'company_id' => $company->id,
        'year' => 2026,
        'month' => 3,
        'closed_by_user_id' => $user->id,
    ]);

    $this->actingAsUserInCompany($user, $company)
        ->deleteJson(route('scheduling.month_closures.destroy', ['id' => $closure->id]))
        ->assertForbidden();

    CarbonImmutable::setTestNow();
});

it('admin le tud zárni és újra tud nyitni egy hónapot', function (): void {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-03-15'));

    [, $company] = $this->createTenantWithCompany();
    $admin = $this->createAdminUser($company);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $admin->refresh();

    $response = $this->actingAsUserInCompany($admin, $company)
        ->postJson(route('scheduling.month_closures.store'), [
            'year' => 2026,
            'month' => 3,
            'note' => 'Havi zárás',
        ])
        ->assertCreated()
        ->assertJsonPath('data.year', 2026)
        ->assertJsonPath('data.month', 3);

    $closureId = (int) $response->json('data.id');

    $this->assertDatabaseHas('month_closures', [
        'id' => $closureId,
        'company_id' => $company->id,
        'year' => 2026,
        'month' => 3,
        'deleted_at' => null,
    ]);

    $this->actingAsUserInCompany($admin, $company)
        ->deleteJson(route('scheduling.month_closures.destroy', ['id' => $closureId]))
        ->assertOk();

    $this->assertSoftDeleted('month_closures', [
        'id' => $closureId,
        'company_id' => $company->id,
    ]);

    CarbonImmutable::setTestNow();
});

it('ugyanaz a hónap nem zárható le kétszer', function (): void {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-03-15'));

    [, $company] = $this->createTenantWithCompany();
    $admin = $this->createAdminUser($company);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $admin->refresh();

    MonthClosure::factory()->create([
        'company_id' => $company->id,
        'year' => 2026,
        'month' => 3,
        'closed_by_user_id' => $admin->id,
    ]);

    $this->actingAsUserInCompany($admin, $company)
        ->postJson(route('scheduling.month_closures.store'), [
            'year' => 2026,
            'month' => 3,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['month']);

    CarbonImmutable::setTestNow();
});
