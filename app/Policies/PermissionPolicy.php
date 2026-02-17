<?php

declare(strict_types=1);

namespace App\Policies;

//use App\Models\Permission;
use Spatie\Permission\Models\Permission;
use App\Models\User;
use App\Policies\BasePolicy;

final class PermissionPolicy extends BasePolicy
{
    public const PERM_VIEW_ANY = 'permissions.viewAny';
    public const PERM_VIEW = 'permissions.view';
    public const PERM_CREATE = 'permissions.create';
    public const PERM_UPDATE = 'permissions.update';
    public const PERM_UPDATE_ANY = 'permissions.updateAny';
    public const PERM_UPDATE_SELF = 'permissions.updateSelf';
    public const PERM_DELETE = 'permissions.delete';
    public const PERM_DELETE_ANY = 'permissions.deleteAny';
    protected static function entity(): string { return 'permissions'; }

    public function viewAny(User $user): bool
    {
        return $user->can(self::perm(self::PERM_VIEW_ANY));
    }

    public function view(User $user, Permission $permission): bool
    {
        return $user->can(self::perm(self::PERM_VIEW));
    }

    public function create(User $user): bool
    {
        return $user->can(self::perm(self::PERM_CREATE));
    }

    public function update(User $user, Permission $permission): bool
    {
        return $user->can(self::perm(self::PERM_UPDATE));
    }

    public function updateAny(User $user): bool
    {
        return $user->can(self::perm(self::PERM_UPDATE_ANY));
    }

    public function updateSelf(User $user, Permission $permission): bool
    {
        return $user->can(self::perm(self::PERM_UPDATE_SELF));
    }

    public function delete(User $user, Permission $permission): bool
    {
        return $user->can(self::perm(self::PERM_DELETE));
    }

    public function deleteAny(User $user): bool
    {
        return $user->can(self::perm(self::PERM_DELETE_ANY));
    }
}
