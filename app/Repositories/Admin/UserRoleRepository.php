<?php

declare(strict_types=1);

namespace App\Repositories\Admin;

use App\Models\Admin\Role;
use App\Models\User;
use App\Services\Cache\CacheVersionService;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

final class UserRoleRepository
{
    private const NS_USERS_FETCH = 'users.fetch';
    private const NS_SELECTORS_USERS = 'selectors.users';
    private const NS_ROLES_FETCH = 'roles.fetch';
    private const NS_SELECTORS_ROLES = 'selectors.roles';
    private const NS_DASHBOARD_STATS = 'dashboard.stats';

    public function __construct(
        private readonly CacheVersionService $cacheVersionService,
    ) {}

    public function setPrimaryRole(User $user, Role $role): User
    {
        return DB::transaction(function () use ($user, $role): User {
            /** @var User $lockedUser */
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            $lockedUser->syncRoles([$role->name]);
            $lockedUser->load('roles:id,name');

            app(PermissionRegistrar::class)->forgetCachedPermissions();
            $this->invalidateAfterWrite();

            return $lockedUser;
        });
    }

    private function invalidateAfterWrite(): void
    {
        DB::afterCommit(function (): void {
            $this->cacheVersionService->bump(self::NS_USERS_FETCH);
            $this->cacheVersionService->bump(self::NS_SELECTORS_USERS);
            $this->cacheVersionService->bump(self::NS_ROLES_FETCH);
            $this->cacheVersionService->bump(self::NS_SELECTORS_ROLES);
            $this->cacheVersionService->bump(self::NS_DASHBOARD_STATS);
        });
    }
}
