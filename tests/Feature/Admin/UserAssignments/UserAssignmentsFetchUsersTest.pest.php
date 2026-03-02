<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\User;
use App\Models\TenantGroup;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('a user assignments users fetch visszaadja a primary_role_name mezőt és nem defaultol userre role nélküli user esetén', function (): void {
    $tenantGroup = TenantGroup::factory()->create();
    $company = Company::factory()->create([
        'tenant_group_id' => (int) $tenantGroup->id,
        'active' => true,
    ]);

    $admin = $this->createAdminUser($company);
    $admin->givePermissionTo(['user_assignments.viewAny', 'user_assignments.update']);

    $operator = User::factory()->create();
    $operator->companies()->syncWithoutDetaching([$company->id]);
    $operator->assignRole('operator');

    $noRole = User::factory()->create();
    $noRole->companies()->syncWithoutDetaching([$company->id]);
    $noRole->syncRoles([]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $admin->refresh();

    $response = $this
        ->actingAsUserInCompany($admin, $company)
        ->getJson(route('admin.user_assignments.users.fetch'));

    $response->assertOk();

    $items = collect($response->json('data'));
    $operatorPayload = $items->firstWhere('id', $operator->id);
    $noRolePayload = $items->firstWhere('id', $noRole->id);

    expect($operatorPayload)->not()->toBeNull();
    expect($operatorPayload['primary_role_name'])->toBe('operator');
    expect($operatorPayload['is_superadmin'])->toBeFalse();

    expect($noRolePayload)->not()->toBeNull();
    expect($noRolePayload['primary_role_name'])->toBeNull();
    expect($noRolePayload['is_superadmin'])->toBeFalse();
});
