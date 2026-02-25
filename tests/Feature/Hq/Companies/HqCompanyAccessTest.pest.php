<?php

declare(strict_types=1);

use App\Models\TenantGroup;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
    TenantGroup::forgetCurrent();
});

afterEach(function (): void {
    TenantGroup::forgetCurrent();
});

it('returns 403 for non-superadmin on hq companies index', function (): void {
    $user = $this->createAdminUser();

    $this->actingAs($user)
        ->get(route('hq.companies.index'))
        ->assertForbidden();
});

it('returns 200 for superadmin on hq companies index', function (): void {
    $user = $this->createSuperadminUser();

    $this->actingAs($user)
        ->get(route('hq.companies.index'))
        ->assertOk();
});
