<?php

declare(strict_types=1);

namespace App\Repositories\Admin;

use App\Models\Admin\Role;
use App\Models\User;
use App\Services\Cache\CacheVersionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

final class RoleUsersRepository
{
    private const NS_USERS_FETCH = 'users.fetch';
    private const NS_SELECTORS_USERS = 'selectors.users';
    private const NS_ROLES_FETCH = 'roles.fetch';
    private const NS_SELECTORS_ROLES = 'selectors.roles';
    private const NS_DASHBOARD_STATS = 'dashboard.stats';

    public function __construct(
        private readonly CacheVersionService $cacheVersionService,
    ) {}

    /**
     * @param list<int> $userIds
     */
    public function syncUsers(Role $role, array $userIds): Role
    {
        return DB::transaction(function () use ($role, $userIds): Role {
            /** @var Role $lockedRole */
            $lockedRole = Role::query()->with('users:id')->lockForUpdate()->findOrFail($role->id);

            $normalizedIds = User::query()
                ->whereIn('id', $userIds)
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->unique()
                ->values()
                ->all();

            $this->guardAgainstRemovingLastOwnRole($lockedRole, $normalizedIds);

            $lockedRole->users()->sync($normalizedIds);
            $lockedRole->load(['users:id,name,email']);
            $lockedRole->loadCount('users');

            app(PermissionRegistrar::class)->forgetCachedPermissions();
            $this->invalidateAfterWrite();

            return $lockedRole;
        });
    }

    /**
     * @param list<int> $nextUserIds
     */
    private function guardAgainstRemovingLastOwnRole(Role $role, array $nextUserIds): void
    {
        $authUser = Auth::user();
        if (! $authUser instanceof User) {
            return;
        }

        $hasRoleCurrently = $authUser->roles()
            ->where('roles.id', $role->id)
            ->exists();

        if (! $hasRoleCurrently) {
            return;
        }

        $willKeepRole = \in_array((int) $authUser->id, $nextUserIds, true);
        if ($willKeepRole) {
            return;
        }

        $roleCount = $authUser->roles()->count();
        if ($roleCount > 1) {
            return;
        }

        throw ValidationException::withMessages([
            'user_ids' => 'A saját felhasználódat nem hagyhatod szerepkör nélkül.',
        ]);
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
