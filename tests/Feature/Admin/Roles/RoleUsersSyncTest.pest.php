<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Cache\CacheVersionService;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
});

it('megtagadja a role user syncet, ha nincs jogosultság', function (): void {
    $admin = $this->createAdminUser();
    $admin->syncRoles([]);
    $admin->syncPermissions([]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $admin->refresh();

    $role = Role::findByName('operator', 'web');
    $target = User::factory()->create();

    $this->actingAs($admin)
        ->patchJson(route('admin.roles.users.update', ['role' => $role->id]), [
            'user_ids' => [$target->id],
        ])
        ->assertForbidden();
});

it('lehetővé teszi adminnak a role felhasználóinak syncelését és frissíti a users_count mezőt', function (): void {
    $admin = $this->createAdminUser();
    $first = User::factory()->create();
    $second = User::factory()->create();

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $admin->refresh();

    $versioner = app(CacheVersionService::class);
    Cache::forever('v:users.fetch', 1);
    Cache::forever('v:selectors.users', 1);
    Cache::forever('v:roles.fetch', 1);
    Cache::forever('v:selectors.roles', 1);
    Cache::forever('v:dashboard.stats', 1);

    $role = Role::findByName('operator', 'web');
    $role->users()->sync([]);

    $this->actingAs($admin)
        ->patchJson(route('admin.roles.users.update', ['role' => $role->id]), [
            'user_ids' => [$first->id, $second->id],
        ])
        ->assertOk()
        ->assertJsonPath('data.users_count', 2);

    $role->refresh()->loadCount('users');

    expect($role->users_count)->toBe(2);
    expect($role->users()->pluck('users.id')->sort()->values()->all())
        ->toBe(collect([$first->id, $second->id])->sort()->values()->all());
    expect($versioner->get('users.fetch'))->toBe(2);
    expect($versioner->get('selectors.users'))->toBe(2);
    expect($versioner->get('roles.fetch'))->toBe(2);
    expect($versioner->get('selectors.roles'))->toBe(2);
});

it('nem engedi, hogy a jelenlegi user az egyetlen role-ját elveszítse sync közben', function (): void {
    $admin = $this->createAdminUser();
    $admin->syncRoles(['admin']);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $admin->refresh();

    $role = Role::findByName('admin', 'web');

    $this->actingAs($admin)
        ->patchJson(route('admin.roles.users.update', ['role' => $role->id]), [
            'user_ids' => [],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['user_ids']);
});
